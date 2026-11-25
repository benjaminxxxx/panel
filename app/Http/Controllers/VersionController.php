<?php

namespace App\Http\Controllers;

use App\Licencias\PublicadorVersiones;
use App\Models\Cliente;
use App\Models\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VersionController extends Controller
{
    public function index(): View
    {
        return view('versiones.index', [
            'versiones' => Version::all()->sort(fn ($a, $b) => version_compare($b->version, $a->version)),
            'ultima' => Version::ultimaPublicada(),
            'clientes' => Cliente::orderBy('nombre')->get(['nombre', 'version_reportada', 'ultimo_contacto_at']),
        ]);
    }

    public function store(Request $request, PublicadorVersiones $publicador): RedirectResponse
    {
        $datos = $request->validate([
            'version' => ['required', 'regex:/^\d+\.\d+\.\d+$/', 'unique:versiones,version'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'max:204800'], // 200 MB
        ], ['version.regex' => 'Usa el formato 1.0.1']);

        $v = $publicador->publicar($datos['version'], $request->file('archivo')->getRealPath(), $datos['notas'] ?? null, $request->boolean('publicar'));

        return back()->with('ok', "Versión {$v->version} subida".($v->publicada ? ' y publicada: los locales la verán como actualización disponible.' : ' (sin publicar).'));
    }

    public function publicar(Version $version): RedirectResponse
    {
        $version->update(['publicada' => ! $version->publicada]);

        return back()->with('ok', "Versión {$version->version} ".($version->publicada ? 'publicada.' : 'retirada.'));
    }
}
