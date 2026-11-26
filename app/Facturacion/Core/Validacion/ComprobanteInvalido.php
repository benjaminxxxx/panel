<?php

namespace App\Facturacion\Core\Validacion;

use DomainException;

/**
 * El comprobante no cumple las reglas: no se numera ni se envía a SUNAT.
 */
final class ComprobanteInvalido extends DomainException
{
    /**
     * @param  array<string, list<string>>  $errores  campo => mensajes
     */
    public function __construct(public readonly array $errores)
    {
        $primero = array_values($errores)[0][0] ?? 'Comprobante inválido.';

        parent::__construct($primero);
    }
}
