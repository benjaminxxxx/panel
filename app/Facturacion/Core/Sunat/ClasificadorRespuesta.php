<?php

namespace App\Facturacion\Core\Sunat;

use App\Facturacion\Core\Enums\EstadoComprobante;

/**
 * Traduce los códigos de SUNAT a un estado del comprobante.
 *
 * Rangos (Reglas de validación SUNAT):
 *   0            → aceptado
 *   0100 - 1999  → excepción: SUNAT no procesó el comprobante → se puede reenviar el mismo XML
 *   2000 - 3999  → rechazo: el comprobante no existe para SUNAT
 *   4000+        → observación: aceptado, corregir en siguientes emisiones
 *
 * OJO: los rechazos pueden llegar como CDR o como SOAP Fault (isSuccess() = false).
 * Tratar todo fault como "error de comunicación" es un bug común: deja comprobantes
 * rechazados marcados como pendientes de reenvío.
 */
final class ClasificadorRespuesta
{
    /**
     * @param  list<string>  $notas
     */
    public static function desdeCdr(string $codigo, array $notas): EstadoComprobante
    {
        $numero = (int) $codigo;

        return match (true) {
            $numero === 0 => $notas === [] ? EstadoComprobante::Aceptado : EstadoComprobante::Observado,
            $numero >= 4000 => EstadoComprobante::Observado,
            $numero >= 2000 => EstadoComprobante::Rechazado,
            default => EstadoComprobante::ErrorEnvio,
        };
    }

    /**
     * Error devuelto como SOAP Fault o fallo HTTP. Los códigos no numéricos (HTTP, timeouts) son reintentables.
     */
    public static function desdeError(?string $codigo): EstadoComprobante
    {
        if ($codigo === null || ! ctype_digit($codigo)) {
            return EstadoComprobante::ErrorEnvio;
        }

        $numero = (int) $codigo;

        return match (true) {
            $numero >= 2000 && $numero < 4000 => EstadoComprobante::Rechazado,
            default => EstadoComprobante::ErrorEnvio,
        };
    }
}
