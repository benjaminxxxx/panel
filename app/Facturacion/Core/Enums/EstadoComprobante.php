<?php

namespace App\Facturacion\Core\Enums;

enum EstadoComprobante: string
{
    /** Registrado y numerado, aún no enviado. */
    case Pendiente = 'pendiente';

    /** CDR con código 0 y sin observaciones. */
    case Aceptado = 'aceptado';

    /** CDR aceptado con observaciones (códigos 4000+). Es válido, pero hay que corregir en próximas emisiones. */
    case Observado = 'observado';

    /** SUNAT rechazó (códigos 2000-3999). El comprobante NO existe para SUNAT. */
    case Rechazado = 'rechazado';

    /** No se obtuvo respuesta válida (red, timeout, excepción 0100-1999). Se puede reenviar el mismo XML. */
    case ErrorEnvio = 'error_envio';

    public function esFinal(): bool
    {
        return in_array($this, [self::Aceptado, self::Observado, self::Rechazado], true);
    }

    public function esValido(): bool
    {
        return in_array($this, [self::Aceptado, self::Observado], true);
    }

    public function puedeReenviarse(): bool
    {
        return in_array($this, [self::Pendiente, self::ErrorEnvio], true);
    }
}
