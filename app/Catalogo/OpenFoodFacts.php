<?php

namespace App\Catalogo;

use App\Models\CatalogoProducto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Productos de Open Food Facts (base de datos abierta, licencia ODbL) para el catálogo de las
 * panaderías. Se usa su API oficial, no el HTML de la página: es más rápida y estable.
 *
 * Solo entran productos de categorías que se venden en una panadería o pastelería
 * (config/catalogo.php): leche, mantequilla, harina, gaseosas, chocolates, galletas...
 */
class OpenFoodFacts
{
    /**
     * Ficha de un producto por su código de barras. null = Open Food Facts no lo tiene.
     *
     * @return array{codigo_barras: string, nombre: string, marca: string, presentacion: string, categoria: ?string, foto_url: string}|null
     */
    public function producto(string $codigo): ?array
    {
        try {
            $r = $this->http()->get(str_replace('{codigo}', $codigo, (string) config('catalogo.off_producto')), [
                'fields' => 'code,product_name,product_name_es,generic_name_es,brands,quantity,categories_tags,image_front_url,image_front_small_url',
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Open Food Facts sin respuesta', ['codigo' => $codigo, 'error' => $e->getMessage()]);

            return null;
        }
        $p = $r->successful() && (int) $r->json('status') === 1 ? (array) $r->json('product') : null;
        if (! $p) {
            return null;
        }
        $nombre = trim((string) ($p['product_name_es'] ?? '') ?: (string) ($p['product_name'] ?? '') ?: (string) ($p['generic_name_es'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        return [
            'codigo_barras' => $codigo,
            'nombre' => mb_substr($nombre, 0, 120),
            'marca' => mb_substr(trim(explode(',', (string) ($p['brands'] ?? ''))[0]), 0, 80),
            'presentacion' => mb_substr(trim((string) ($p['quantity'] ?? '')), 0, 60),
            'categoria' => $this->categoria((array) ($p['categories_tags'] ?? [])),
            'foto_url' => (string) ($p['image_front_small_url'] ?? $p['image_front_url'] ?? ''),
        ];
    }

    /**
     * Categoría del catálogo para las categorías de Open Food Facts. null = no es de panadería.
     *
     * @param  array<int, mixed>  $etiquetas
     */
    public function categoria(array $etiquetas): ?string
    {
        foreach ((array) config('catalogo.categorias_panaderia') as $etiqueta => $categoria) {
            if (in_array($etiqueta, $etiquetas, true)) {
                return (string) $categoria;
            }
        }

        return null;
    }

    /**
     * Códigos de productos del país en una categoría (buscador oficial search.openfoodfacts.org).
     *
     * @return array{codigos: list<string>, paginas: int}
     */
    public function codigosDeCategoria(string $etiqueta, int $pagina = 1, int $porPagina = 100): array
    {
        $consulta = sprintf('countries:"%s" AND categories:"%s"', config('catalogo.off_pais'), $etiqueta);
        try {
            $r = $this->http()->get((string) config('catalogo.off_busqueda'), [
                'q' => $consulta, 'page' => $pagina, 'page_size' => $porPagina, 'fields' => 'code',
            ]);
        } catch (ConnectionException) {
            return ['codigos' => [], 'paginas' => 0];
        }
        $codigos = array_values(array_filter(array_map(
            fn ($h) => is_array($h) ? (string) ($h['code'] ?? '') : '',
            (array) $r->json('hits', []),
        ), fn (string $c) => preg_match('/^\d{8,14}$/', $c) === 1));

        return ['codigos' => $codigos, 'paginas' => (int) $r->json('page_count', 0)];
    }

    /**
     * Agrega al catálogo un producto de Open Food Facts (con su foto) si es de panadería.
     * Devuelve el producto guardado, o null si no existe o no es de panadería.
     */
    public function importar(string $codigo): ?CatalogoProducto
    {
        if ($existente = CatalogoProducto::where('codigo_barras', $codigo)->first()) {
            return $existente;
        }
        $datos = $this->producto($codigo);

        return $datos && $datos['categoria'] !== null ? $this->guardar($datos) : null;
    }

    /** @param  array{codigo_barras: string, nombre: string, marca: string, presentacion: string, categoria: ?string, foto_url: string}  $datos */
    private function guardar(array $datos): CatalogoProducto
    {
        $codigo = $datos['codigo_barras'];

        return CatalogoProducto::create([
            'codigo_barras' => $codigo,
            'nombre' => $datos['nombre'],
            'marca' => $datos['marca'] ?: null,
            'presentacion' => $datos['presentacion'] ?: null,
            'categoria' => $datos['categoria'],
            'unidad' => 'NIU',
            'foto' => $this->guardarFoto($codigo, $datos['foto_url']),
            'fuente' => 'openfoodfacts',
        ]);
    }

    /**
     * Para el escaneo desde una panadería: el producto del catálogo, o lo trae de Open Food Facts si
     * es de panadería. Si Open Food Facts lo conoce pero no es de panadería, devuelve su nombre como
     * sugerencia (para registrarlo a mano) sin agregarlo al catálogo.
     *
     * @return array{producto: ?CatalogoProducto, sugerencia: ?array{nombre: string, marca: string, presentacion: string}}
     */
    public function buscarPorCodigo(string $codigo): array
    {
        $producto = CatalogoProducto::where('codigo_barras', $codigo)->where('activo', true)->first();
        if ($producto) {
            return ['producto' => $producto, 'sugerencia' => null];
        }
        $datos = $this->producto($codigo);
        if ($datos && $datos['categoria'] !== null && ! CatalogoProducto::where('codigo_barras', $codigo)->exists()) {
            return ['producto' => $this->guardar($datos), 'sugerencia' => null];
        }

        return ['producto' => null, 'sugerencia' => $datos ? ['nombre' => $datos['nombre'], 'marca' => $datos['marca'], 'presentacion' => $datos['presentacion']] : null];
    }

    /** Descarga la foto del producto al disco del panel (las panaderías la bajan de aquí). */
    private function guardarFoto(string $codigo, string $url): ?string
    {
        if (! str_starts_with($url, 'https://images.openfoodfacts.org/')) {
            return null;
        }
        try {
            $r = $this->http()->get($url);
        } catch (ConnectionException) {
            return null;
        }
        if (! $r->successful() || ! str_starts_with((string) $r->header('Content-Type'), 'image/')) {
            return null;
        }
        $ruta = "catalogo/off-{$codigo}.jpg";
        Storage::disk('local')->put($ruta, $r->body());

        return $ruta;
    }

    private function http(): PendingRequest
    {
        return Http::withUserAgent((string) config('catalogo.off_agente'))->acceptJson()->timeout(8)->connectTimeout(5); // la panadería espera hasta 30 s en total
    }
}
