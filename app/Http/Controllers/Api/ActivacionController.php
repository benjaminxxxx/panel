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
     * El programa dice qué sistema es (los del restaurante anteriores no lo envían): un
     * código de la panadería no activa un restaurante ni al revés.
     */
    public function activar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'aplicacion' => ['nullable', 'string', 'max:20'],
        ]);
        $aplicacion = $datos['aplicacion'] ?? 'restaurante';

        return DB::transaction(function () use ($datos, $aplicacion) {
            $cliente = Cliente::porCodigoActivacion($datos['codigo']);
            if (! $cliente) {
                return response()->json(['error' => 'Código inválido o vencido. Pide uno nuevo a tu proveedor.'], 422);
            }
            if ($cliente->aplicacion !== $aplicacion) {
                $sistema = config("licencias.aplicaciones.{$cliente->aplicacion}", $cliente->aplicacion);

                return response()->json(['error' => "Este código es para el sistema {$sistema}, no para este programa."], 422);
            }
            $clave = $cliente->nuevaClave();
            $cliente->forceFill(['activacion_hash' => null, 'activacion_expira_at' => null])->save();

            return response()->json(['clave' => $clave, 'cliente' => $cliente->codigo, 'nombre' => $cliente->nombre]);
        });
    }
}
