<?php

namespace App\Http\Controllers;

use App\Licencias\PublicadorVersiones;
use App\Models\Cliente;
use App\Models\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VersionController extends Controller
{
    public function index(): View
    {
        return view('versiones.index', [
            // Por sistema y, dentro de cada uno, la versión más nueva primero (1.0.10 > 1.0.9).
            'versiones' => Version::all()->sort(fn (Version $a, Version $b) => strcmp($a->aplicacion, $b->aplicacion)
                ?: strcmp($a->plataforma, $b->plataforma) ?: version_compare($b->version, $a->version)),
            'ultimas' => collect(Version::aplicaciones())->mapWithKeys(fn ($n, $app) => [$app => Version::ultimaPublicada('windows', $app)]),
            'clientes' => Cliente::orderBy('aplicacion')->orderBy('nombre')->get(['aplicacion', 'nombre', 'version_reportada', 'ultimo_contacto_at']),
            // Si el hosting no deja subir archivos grandes, el formulario falla sin explicación: se avisa antes.
            'limiteSubida' => min($this->bytes((string) ini_get('upload_max_filesize')), $this->bytes((string) ini_get('post_max_size'))),
        ]);
    }

    public function store(Request $request, PublicadorVersiones $publicador): RedirectResponse
    {
        $plataforma = in_array($request->input('plataforma'), Version::PLATAFORMAS, true) ? $request->input('plataforma') : 'windows';
        $aplicacion = array_key_exists((string) $request->input('aplicacion'), Version::aplicaciones()) ? $request->input('aplicacion') : 'restaurante';
        $datos = $request->validate([
            'version' => ['required', 'regex:/^\d+\.\d+\.\d+$/',
                Rule::unique('versiones', 'version')->where('plataforma', $plataforma)->where('aplicacion', $aplicacion)],
            'notas' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'max:204800'], // 200 MB
        ], ['version.regex' => 'Usa el formato 1.0.1', 'version.unique' => 'Esa versión ya existe para ese sistema y plataforma']);

        $v = $publicador->publicar($datos['version'], $request->file('archivo')->getRealPath(), $datos['notas'] ?? null,
            $request->boolean('publicar'), $plataforma, $aplicacion);
        $sistema = Version::aplicaciones()[$aplicacion];
        $quien = $plataforma === 'android' ? 'las tablets y celulares' : "los locales de {$sistema}";

        return back()->with('ok', "{$sistema} {$v->version} ({$plataforma}) subida".($v->publicada ? " y publicada: {$quien} la verán como actualización disponible." : ' (sin publicar).'));
    }

    /** "8M", "200M", "1G" → bytes (0 = sin límite). */
    private function bytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '-1' || $valor === '0') {
            return PHP_INT_MAX;
        }
        $numero = (int) $valor;

        return match (strtoupper(substr($valor, -1))) {
            'G' => $numero * 1024 ** 3,
            'M' => $numero * 1024 ** 2,
            'K' => $numero * 1024,
            default => $numero,
        };
    }

    public function publicar(Version $version): RedirectResponse
    {
        $version->update(['publicada' => ! $version->publicada]);

        return back()->with('ok', "Versión {$version->version} ".($version->publicada ? 'publicada.' : 'retirada.'));
    }
}
