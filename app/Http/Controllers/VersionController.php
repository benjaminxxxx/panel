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
            'versiones' => Version::all()->sort(fn ($a, $b) => [$a->aplicacion, version_compare($b->version, $a->version)] <=> [$b->aplicacion, 0]),
            'ultimas' => collect(Version::aplicaciones())->mapWithKeys(fn ($n, $app) => [$app => Version::ultimaPublicada('windows', $app)]),
            'clientes' => Cliente::orderBy('aplicacion')->orderBy('nombre')->get(['aplicacion', 'nombre', 'version_reportada', 'ultimo_contacto_at']),
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

    public function publicar(Version $version): RedirectResponse
    {
        $version->update(['publicada' => ! $version->publicada]);

        return back()->with('ok', "Versión {$version->version} ".($version->publicada ? 'publicada.' : 'retirada.'));
    }
}
