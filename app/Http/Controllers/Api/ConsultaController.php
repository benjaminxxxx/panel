<?php

namespace App\Http\Controllers\Api;

use App\Consultas\ConsultaNoDisponible;
use App\Consultas\ConsultaSunat;
use App\Facturacion\Core\Validacion\Ruc;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Consulta de RUC y DNI para llenar el nombre exacto del cliente (la factura lo exige).
 *   200 → datos · 404 → no existe · 422 → número mal escrito · 503 → SUNAT no responde (reintentar)
 */
class ConsultaController extends Controller
{
    public function __construct(private readonly ConsultaSunat $consulta) {}

    public function ruc(string $ruc): JsonResponse
    {
        if (! Ruc::esValido($ruc)) {
            return response()->json(['error' => 'El RUC no es válido: revisa los 11 números.'], 422);
        }

        return $this->responder(fn () => $this->consulta->ruc($ruc), 'No existe un contribuyente con ese RUC.');
    }

    public function dni(string $dni): JsonResponse
    {
        if (! preg_match('/^\d{8}$/', $dni)) {
            return response()->json(['error' => 'El DNI tiene 8 números.'], 422);
        }

        return $this->responder(fn () => $this->consulta->dni($dni),
            'No se encontró el DNI. Escribe el nombre a mano (las fuentes públicas solo tienen a quienes tienen RUC).');
    }

    /** @param  callable(): ?array<string, mixed>  $buscar */
    private function responder(callable $buscar, string $noEncontrado): JsonResponse
    {
        try {
            $datos = $buscar();
        } catch (ConsultaNoDisponible $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }

        return $datos ? response()->json($datos) : response()->json(['error' => $noEncontrado], 404);
    }
}
