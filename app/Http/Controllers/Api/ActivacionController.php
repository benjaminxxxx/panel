<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivacionController extends Controller
{
    /**
     * El local recién instalado canjea el código por su clave. El código se consume y
     * la clave anterior deja de valer: un cliente tiene una sola instalación activa.
     */
    public function activar(Request $request): JsonResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:20']]);

        return DB::transaction(function () use ($datos) {
            $cliente = Cliente::porCodigoActivacion($datos['codigo']);
            if (! $cliente) {
                return response()->json(['error' => 'Código inválido o vencido. Pide uno nuevo a tu proveedor.'], 422);
            }
            $clave = $cliente->nuevaClave();
            $cliente->forceFill(['activacion_hash' => null, 'activacion_expira_at' => null])->save();

            return response()->json(['clave' => $clave, 'cliente' => $cliente->codigo, 'nombre' => $cliente->nombre]);
        });
    }
}
