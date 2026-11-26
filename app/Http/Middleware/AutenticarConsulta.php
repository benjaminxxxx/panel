<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Models\EmpresaToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consultas de RUC/DNI: las pueden usar los programas de los locales (su clave rst_...), los
 * sistemas conectados a facturación (token fk_... de una empresa) o el token de consultas.
 * Acepta también ?token= para compatibilidad con el servicio anterior.
 */
class AutenticarConsulta
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) ($request->bearerToken() ?: $request->query('token', ''));
        $general = (string) config('consultas.token');
        $valido = $token !== '' && (
            ($general !== '' && hash_equals($general, $token))
            || Cliente::porClave($token) !== null
            || EmpresaToken::buscarActivo($token) !== null
        );
        if (! $valido) {
            return response()->json(['error' => 'token inválido'], 401);
        }

        return $next($request);
    }
}
