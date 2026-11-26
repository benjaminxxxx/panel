<?php

namespace App\Http\Middleware;

use App\Models\EmpresaToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica al sistema consumidor (pos, hotel, ...) con "Authorization: Bearer fk_...".
 * Deja en el request la empresa emisora y el sistema origen.
 */
class AutenticarSistema
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = EmpresaToken::buscarActivo((string) $request->bearerToken());

        if (! $token) {
            return response()->json(['message' => 'Token de API inválido o revocado.'], 401);
        }

        if (! $token->empresa->activo) {
            return response()->json(['message' => 'La empresa está desactivada.'], 403);
        }

        if (! $token->ultimo_uso_at || $token->ultimo_uso_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['ultimo_uso_at' => now()])->saveQuietly();
        }

        $request->attributes->set('empresa', $token->empresa);
        $request->attributes->set('sistema', $token->sistema);

        return $next($request);
    }
}
