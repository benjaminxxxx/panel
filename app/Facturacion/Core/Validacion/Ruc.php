<?php

namespace App\Facturacion\Core\Validacion;

final class Ruc
{
    private const PESOS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    private const PREFIJOS = ['10', '15', '16', '17', '20'];

    /**
     * Valida formato, prefijo y dígito verificador (módulo 11).
     */
    public static function esValido(string $ruc): bool
    {
        if (! preg_match('/^\d{11}$/', $ruc) || ! in_array(substr($ruc, 0, 2), self::PREFIJOS, true)) {
            return false;
        }

        $suma = 0;
        foreach (self::PESOS as $i => $peso) {
            $suma += (int) $ruc[$i] * $peso;
        }

        $digito = 11 - ($suma % 11);
        $digito = match ($digito) {
            10 => 0,
            11 => 1,
            default => $digito,
        };

        return $digito === (int) $ruc[10];
    }
}
