<?php

namespace App\Facturacion\Core\Dominio;

use App\Facturacion\Core\Enums\TipoComprobante;
use DateTimeImmutable;

/**
 * Comprobante completo, numerado y calculado: listo para generar el XML.
 */
final readonly class DocumentoElectronico
{
    /**
     * @param  list<LineaCalculada>  $lineas
     * @param  DocumentoAfectado|null  $afectado  Solo en notas de crédito.
     */
    public function __construct(
        public Emisor $emisor,
        public TipoComprobante $tipo,
        public string $serie,
        public int $correlativo,
        public DateTimeImmutable $fechaEmision,
        public string $moneda,
        public Cliente $cliente,
        public array $lineas,
        public Totales $totales,
        public string $montoEnLetras,
        public ?string $observacion = null,
        public ?DocumentoAfectado $afectado = null,
        public ?Detraccion $detraccion = null,
    ) {}

    /**
     * Nombre SUNAT: RUC-TIPO-SERIE-CORRELATIVO (ej: 20123456789-01-F001-1).
     */
    public function nombre(): string
    {
        return "{$this->emisor->ruc}-{$this->tipo->value}-{$this->serie}-{$this->correlativo}";
    }

    public function numero(): string
    {
        return "{$this->serie}-{$this->correlativo}";
    }
}
