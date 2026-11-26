<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Cada local ve solo las versiones de su sistema (restaurante, panadería...). */
class ActualizacionController extends Controller
{
    public function ultima(Request $request): JsonResponse
    {
        $ultima = Version::ultimaPublicada('windows', $this->cliente($request)->aplicacion);
        $actual = (string) $request->query('version', '0.0.0');
        if (! $ultima || version_compare($ultima->version, $actual, '<=')) {
            return response()->json(['disponible' => false]);
        }

        return response()->json([
            'disponible' => true,
            'version' => $ultima->version,
            'notas' => (string) $ultima->notas,
            'sha256' => $ultima->sha256,
            'firma' => $ultima->firma,
            'tamano' => $ultima->tamano,
        ]);
    }

    public function descargar(Request $request, string $version): StreamedResponse
    {
        $v = Version::where('aplicacion', $this->cliente($request)->aplicacion)->where('plataforma', 'windows')
            ->where('version', $version)->where('publicada', true)->firstOrFail();

        return Storage::disk('local')->download($v->archivo, $v->nombreArchivo());
    }

    private function cliente(Request $request): Cliente
    {
        return $request->attributes->get('cliente');
    }
}
