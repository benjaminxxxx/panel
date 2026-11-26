<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Licencias\PublicadorVersiones;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicacionController extends Controller
{
    /**
     * Publica un instalador desde Git (GitHub Actions). El token solo permite subir versiones;
     * la firma la pone el panel con su llave privada, que nunca sale de aquí.
     * "aplicacion" indica el sistema (por defecto restaurante, como antes).
     */
    public function store(Request $request, PublicadorVersiones $publicador): JsonResponse
    {
        $token = (string) config('licencias.token_publicacion');
        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['error' => 'token de publicación inválido'], 401);
        }
        $aplicacion = (string) $request->input('aplicacion', 'restaurante');
        $datos = $request->validate([
            'aplicacion' => ['nullable', Rule::in(array_keys(Version::aplicaciones()))],
            'version' => ['required', 'regex:/^\d+\.\d+\.\d+$/',
                Rule::unique('versiones', 'version')->where('plataforma', 'windows')->where('aplicacion', $aplicacion)],
            'notas' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'max:204800'],
        ]);
        $v = $publicador->publicar($datos['version'], $request->file('archivo')->getRealPath(), $datos['notas'] ?? null,
            $request->boolean('publicar', true), 'windows', $aplicacion);

        return response()->json(['aplicacion' => $v->aplicacion, 'version' => $v->version, 'sha256' => $v->sha256, 'publicada' => $v->publicada], 201);
    }
}
