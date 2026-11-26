<?php

namespace App\Facturacion\Core\Dominio;

use App\Facturacion\Core\Enums\TipoComprobante;
use DateTimeImmutable;

final readonly class SolicitudComprobante
{
    /**
     * @param  list<ItemSolicitud>  $items
     * @param  DocumentoAfectado|null  $afectado  Obligatorio en una nota de crédito: el comprobante que modifica.
     */
    public function __construct(
        public TipoComprobante $tipo,
        public string $serie,
        public DateTimeImmutable $fechaEmision,
        public Cliente $cliente,
        public array $items,
        public string $moneda = 'PEN',
        public ?string $observacion = null,
        public ?DocumentoAfectado $afectado = null,
        public ?Detraccion $detraccion = null,
    ) {}
}
