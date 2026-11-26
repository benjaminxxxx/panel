<?php

namespace App\Facturacion\Core\Enums;

use BackedEnum;

/**
 * Motivo de una nota de crédito (catálogo 09) o de débito (catálogo 10).
 */
interface MotivoNota extends BackedEnum
{
    public function descripcion(): string;

    /**
     * La nota copia el comprobante completo (mismos ítems y total): no se envían ítems.
     */
    public function esTotal(): bool;
}
