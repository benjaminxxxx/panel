<?php

namespace App\Facturacion\Core\Enums;

/**
 * Catálogo 06 SUNAT.
 */
enum TipoDocumentoIdentidad: string
{
    /** Clientes varios: boletas sin identificar (solo hasta S/ 700). */
    case Varios = '-';
    case SinRuc = '0';
    case Dni = '1';
    case CarnetExtranjeria = '4';
    case Ruc = '6';
    case Pasaporte = '7';
    case CedulaDiplomatica = 'A';
    case DocPaisResidencia = 'B';
    case Tin = 'C';
    case In = 'D';
    case Tam = 'E';
    case Ptp = 'F';

    /**
     * Indica si el documento identifica realmente al adquiriente.
     */
    public function identifica(): bool
    {
        return ! in_array($this, [self::Varios, self::SinRuc], true);
    }
}
