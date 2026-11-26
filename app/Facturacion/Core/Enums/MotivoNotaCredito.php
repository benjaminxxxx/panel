<?php

namespace App\Facturacion\Core\Enums;

/**
 * Catálogo 09 SUNAT: motivos de nota de crédito soportados.
 */
enum MotivoNotaCredito: string implements MotivoNota
{
    case AnulacionOperacion = '01';
    case AnulacionErrorRuc = '02';
    case DescuentoGlobal = '04';
    case DescuentoPorItem = '05';
    case DevolucionTotal = '06';
    case DevolucionPorItem = '07';
    case DisminucionValor = '09';

    public function descripcion(): string
    {
        return match ($this) {
            self::AnulacionOperacion => 'Anulación de la operación',
            self::AnulacionErrorRuc => 'Anulación por error en el RUC',
            self::DescuentoGlobal => 'Descuento global',
            self::DescuentoPorItem => 'Descuento por ítem',
            self::DevolucionTotal => 'Devolución total',
            self::DevolucionPorItem => 'Devolución por ítem',
            self::DisminucionValor => 'Disminución en el valor',
        };
    }

    /**
     * Motivos que revierten el comprobante completo: la nota lleva todos sus ítems y su mismo total.
     * Los demás acreditan solo una parte y deben traer los ítems (lo devuelto o lo descontado).
     */
    public function esTotal(): bool
    {
        return in_array($this, [self::AnulacionOperacion, self::AnulacionErrorRuc, self::DevolucionTotal], true);
    }

    /**
     * SUNAT no admite descuentos (04 y 05) en notas de crédito de boletas: para rebajar una
     * boleta se usa la disminución en el valor (09).
     */
    public function aplicaA(TipoComprobante $tipo): bool
    {
        return $tipo !== TipoComprobante::Boleta || ! in_array($this, [self::DescuentoGlobal, self::DescuentoPorItem], true);
    }
}
