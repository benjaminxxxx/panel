<?php

namespace App\Http\Controllers;

use App\Models\Version;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Lo que el proveedor abre en la PC del local para instalar el sistema (restaurante o panadería). */
class DescargaController extends Controller
{
    public function index(): View
    {
        $ultimas = collect(Version::aplicaciones())->mapWithKeys(fn ($nombre, $app) => [$app => Version::ultimaPublicada('windows', $app)]);

        return view('descargas.index', ['ultimas' => $ultimas, 'ultima' => $ultimas['restaurante'] ?? null]);
    }

    public function windows(string $aplicacion = 'restaurante'): StreamedResponse
    {
        abort_unless(array_key_exists($aplicacion, Version::aplicaciones()), 404);
        $v = Version::ultimaPublicada('windows', $aplicacion);
        abort_unless($v, 404, 'Aún no hay ninguna versión publicada.');

        return Storage::disk('local')->download($v->archivo, $v->nombreArchivo());
    }
}
