<?php

namespace App\Facturacion\Core\Validacion;

/**
 * Ubigeo INEI (Catálogo 13 de SUNAT): DDPPDD = departamento, provincia, distrito.
 *
 * Valida la estructura (departamento 01-25, provincia y distrito distintos de 00). No reemplaza al
 * catálogo completo, pero atrapa los valores de relleno como 000000 que SUNAT observa (código 4093).
 */
final class Ubigeo
{
    public static function esValido(string $ubigeo): bool
    {
        if (! preg_match('/^(\d{2})(\d{2})(\d{2})$/', $ubigeo, $m)) {
            return false;
        }

        $departamento = (int) $m[1];

        return $departamento >= 1 && $departamento <= 25 && $m[2] !== '00' && $m[3] !== '00';
    }
}
