<?php

namespace App\Http\Controllers;

use App\Models\Version;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Lo que el proveedor abre en la PC o tablet del restaurante para instalar el sistema. */
class DescargaController extends Controller
{
    public function index(): View
    {
        return view('descargas.index', ['ultima' => Version::ultimaPublicada()]);
    }

    public function windows(): StreamedResponse
    {
        $v = Version::ultimaPublicada();
        abort_unless($v, 404, 'Aún no hay ninguna versión publicada.');

        return Storage::disk('local')->download($v->archivo, "Restaurante-Setup-{$v->version}.exe");
    }
}
