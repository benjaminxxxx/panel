<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El programa del local se identifica con su clave (Authorization: Bearer ...). */
class AutenticarCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $clave = $request->bearerToken();
        $cliente = $clave ? Cliente::porClave($clave) : null;
        if (! $cliente) {
            return response()->json(['error' => 'clave de cliente inválida'], 401);
        }
        $request->attributes->set('cliente', $cliente);

        return $next($request);
    }
}
