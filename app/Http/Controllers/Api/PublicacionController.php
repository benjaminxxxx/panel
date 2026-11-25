<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Licencias\PublicadorVersiones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicacionController extends Controller
{
    /**
     * Publica un instalador desde Git (GitHub Actions). El token solo permite subir versiones;
     * la firma la pone el panel con su llave privada, que nunca sale de aquí.
     */
    public function store(Request $request, PublicadorVersiones $publicador): JsonResponse
    {
        $token = (string) config('licencias.token_publicacion');
        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['error' => 'token de publicación inválido'], 401);
        }
        $datos = $request->validate([
            'version' => ['required', 'regex:/^\d+\.\d+\.\d+$/', 'unique:versiones,version'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'max:204800'],
        ]);
        $v = $publicador->publicar($datos['version'], $request->file('archivo')->getRealPath(), $datos['notas'] ?? null, $request->boolean('publicar', true));

        return response()->json(['version' => $v->version, 'sha256' => $v->sha256, 'publicada' => $v->publicada], 201);
    }
}
