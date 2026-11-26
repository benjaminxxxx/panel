<?php

namespace App\Http\Requests\Api;

use App\Facturacion\Core\Enums\MotivoNotaDebito;

/**
 * Validación estructural de una nota de débito: como la de crédito, pero con el catálogo 10
 * de motivos y siempre con ítems (el concepto que aumenta lo cobrado).
 */
class EmitirNotaDebitoRequest extends EmitirNotaCreditoRequest
{
    protected const MOTIVOS = MotivoNotaDebito::class;

    protected const ITEMS_OBLIGATORIOS = true;
}
