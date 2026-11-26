<?php

namespace App\Facturacion\Core\Enums;

/**
 * Catálogo 01 SUNAT (solo los tipos soportados por ahora).
 */
enum TipoComprobante: string
{
    case Factura = '01';
    case Boleta = '03';
    case NotaCredito = '07';
    case NotaDebito = '08';

    /**
     * Tipos que se emiten por sí solos. Las notas siempre nacen de uno de ellos.
     *
     * @return list<self>
     */
    public static function ventas(): array
    {
        return [self::Factura, self::Boleta];
    }

    /**
     * Nota de crédito o de débito: modifica una factura o boleta ya emitida.
     */
    public function esNota(): bool
    {
        return in_array($this, [self::NotaCredito, self::NotaDebito], true);
    }

    /**
     * Primera letra de la serie. La de una nota depende del comprobante que
     * modifica (F para facturas, B para boletas): ver prefijosSerie().
     */
    public function prefijoSerie(): string
    {
        return match ($this) {
            self::Factura, self::NotaCredito, self::NotaDebito => 'F',
            self::Boleta => 'B',
        };
    }

    /**
     * Primeras letras válidas para una serie de este tipo.
     *
     * @return list<string>
     */
    public function prefijosSerie(): array
    {
        return match ($this) {
            self::Factura => ['F'],
            self::Boleta => ['B'],
            self::NotaCredito, self::NotaDebito => ['F', 'B'],
        };
    }

    /**
     * Expresión regular de una serie válida de este tipo: F001, B001, FC01, BD01...
     */
    public function patronSerie(): string
    {
        return '/^['.implode('', $this->prefijosSerie()).'][A-Z0-9]{3}$/';
    }

    /**
     * Serie de ejemplo para un comprobante cuya serie empieza con esa letra: FC01, BD01, F001.
     */
    public function serieEjemplo(string $prefijo): string
    {
        return $prefijo.match ($this) {
            self::NotaCredito => 'C01',
            self::NotaDebito => 'D01',
            default => '001',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Factura => 'FACTURA ELECTRÓNICA',
            self::Boleta => 'BOLETA DE VENTA ELECTRÓNICA',
            self::NotaCredito => 'NOTA DE CRÉDITO ELECTRÓNICA',
            self::NotaDebito => 'NOTA DE DÉBITO ELECTRÓNICA',
        };
    }

    public function nombre(): string
    {
        return match ($this) {
            self::Factura => 'Factura',
            self::Boleta => 'Boleta',
            self::NotaCredito => 'Nota de crédito',
            self::NotaDebito => 'Nota de débito',
        };
    }
}
