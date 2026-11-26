<?php

namespace App\Facturacion\Core\Enums;

/**
 * Catálogo 10 SUNAT: motivos de nota de débito. Siempre aumenta lo cobrado, así que
 * siempre trae sus propios ítems (el concepto que se suma).
 */
enum MotivoNotaDebito: string implements MotivoNota
{
    case InteresesPorMora = '01';
    case AumentoValor = '02';
    case Penalidades = '03';

    public function descripcion(): string
    {
        return match ($this) {
            self::InteresesPorMora => 'Intereses por mora',
            self::AumentoValor => 'Aumento en el valor',
            self::Penalidades => 'Penalidades / otros conceptos',
        };
    }

    public function esTotal(): bool
    {
        return false;
    }
}
