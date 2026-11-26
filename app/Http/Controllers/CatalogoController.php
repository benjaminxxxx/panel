<?php

namespace App\Http\Controllers;

use App\Models\CatalogoProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** El proveedor mantiene el catálogo público: alta, edición, foto e importación desde CSV. */
class CatalogoController extends Controller
{
    public function index(Request $request): View
    {
        $q = (string) $request->query('q', '');

        return view('catalogo.index', [
            'productos' => CatalogoProducto::buscar($q)->orderBy('nombre')->paginate(50)->withQueryString(),
            'total' => CatalogoProducto::count(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('catalogo.form', ['p' => new CatalogoProducto]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = new CatalogoProducto($this->validar($request));
        $this->guardarFoto($request, $p);
        $p->save();

        return redirect()->route('catalogo.index')->with('ok', "«{$p->nombre}» agregado al catálogo.");
    }

    public function edit(CatalogoProducto $producto): View
    {
        return view('catalogo.form', ['p' => $producto]);
    }

    public function update(Request $request, CatalogoProducto $producto): RedirectResponse
    {
        $producto->fill($this->validar($request, $producto) + ['activo' => $request->boolean('activo')]);
        $this->guardarFoto($request, $producto);
        $producto->save();

        return redirect()->route('catalogo.index')->with('ok', "«{$producto->nombre}» actualizado.");
    }

    /**
     * Importa un CSV (separado por coma o punto y coma, con encabezados en la primera fila):
     * codigo_barras, nombre, marca, presentacion, categoria, unidad, precio_sugerido.
     * Si el código ya existe actualiza sus datos. Sirve para cargar los productos del
     * sistema anterior de un cliente.
     */
    public function importar(Request $request): RedirectResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'max:10240']]);
        $lineas = file($request->file('archivo')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (count($lineas) < 2) {
            return back()->withErrors(['archivo' => 'El archivo no tiene productos.']);
        }
        $primera = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) array_shift($lineas));
        $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        $cabecera = array_map(fn ($c) => strtolower(trim((string) $c)), str_getcsv($primera, $separador));
        foreach (['codigo_barras', 'nombre'] as $obligatoria) {
            if (! in_array($obligatoria, $cabecera, true)) {
                return back()->withErrors(['archivo' => "Falta la columna «{$obligatoria}» en la primera fila."]);
            }
        }

        $creados = 0;
        $actualizados = 0;
        $errores = [];
        DB::transaction(function () use ($lineas, $separador, $cabecera, &$creados, &$actualizados, &$errores) {
            foreach ($lineas as $i => $linea) {
                if (! mb_check_encoding($linea, 'UTF-8')) {
                    $linea = mb_convert_encoding($linea, 'UTF-8', 'ISO-8859-1'); // CSV guardado desde Excel
                }
                $valores = array_pad(array_slice(str_getcsv($linea, $separador), 0, count($cabecera)), count($cabecera), '');
                $fila = array_combine($cabecera, $valores);
                $codigo = (string) preg_replace('/\s+/', '', (string) $fila['codigo_barras']);
                $nombre = trim((string) $fila['nombre']);
                if (! preg_match('/^[0-9A-Za-z-]{4,40}$/', $codigo) || $nombre === '') {
                    $errores[] = 'Fila '.($i + 2).': código o nombre inválido';

                    continue;
                }
                $unidad = strtoupper(trim((string) ($fila['unidad'] ?? ''))) ?: 'NIU';
                $precio = str_replace(',', '.', trim((string) ($fila['precio_sugerido'] ?? '')));
                $texto = fn (string $campo, int $max) => mb_substr(trim((string) ($fila[$campo] ?? '')), 0, $max);

                $p = CatalogoProducto::firstOrNew(['codigo_barras' => $codigo]);
                $p->exists ? $actualizados++ : $creados++;
                $p->fill([
                    'nombre' => mb_substr($nombre, 0, 120),
                    'marca' => $texto('marca', 80) ?: $p->marca,
                    'presentacion' => $texto('presentacion', 60) ?: $p->presentacion,
                    'categoria' => $texto('categoria', 40) ?: $p->categoria,
                    'unidad' => array_key_exists($unidad, CatalogoProducto::UNIDADES) ? $unidad : 'NIU',
                    'precio_sugerido' => is_numeric($precio) ? $precio : $p->precio_sugerido,
                ])->save();
            }
        });

        $respuesta = redirect()->route('catalogo.index')->with('ok', "Importación lista: {$creados} nuevos, {$actualizados} actualizados.");

        return $errores ? $respuesta->withErrors(['archivo' => implode(' · ', array_slice($errores, 0, 10))]) : $respuesta;
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?CatalogoProducto $p = null): array
    {
        $datos = $request->validate([
            'codigo_barras' => ['required', 'regex:/^[0-9A-Za-z-]{4,40}$/', Rule::unique('catalogo_productos', 'codigo_barras')->ignore($p?->id)],
            'nombre' => ['required', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:80'],
            'presentacion' => ['nullable', 'string', 'max:60'],
            'categoria' => ['nullable', 'string', 'max:40'],
            'unidad' => ['required', Rule::in(array_keys(CatalogoProducto::UNIDADES))],
            'precio_sugerido' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ], [
            'codigo_barras.regex' => 'El código solo lleva números o letras (4 a 40).',
            'codigo_barras.unique' => 'Ese código ya está en el catálogo.',
        ]);
        unset($datos['foto']);

        return $datos;
    }

    private function guardarFoto(Request $request, CatalogoProducto $p): void
    {
        if (! $request->hasFile('foto')) {
            return;
        }
        $ruta = $request->file('foto')->store('catalogo', 'local');
        if ($ruta === false) {
            throw ValidationException::withMessages(['foto' => 'No se pudo guardar la foto. Intenta de nuevo.']);
        }
        if ($p->foto) {
            Storage::disk('local')->delete($p->foto);
        }
        $p->foto = $ruta;
    }
}
