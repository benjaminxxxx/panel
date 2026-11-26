<?php

namespace App\Facturacion\Core\Dominio;

use App\Facturacion\Core\Enums\MotivoNota;
use App\Facturacion\Core\Enums\TipoComprobante;

/**
 * Factura o boleta que una nota de crédito o de débito modifica, y por qué.
 */
final readonly class DocumentoAfectado
{
    public function __construct(
        public TipoComprobante $tipo,
        public string $serie,
        public int $correlativo,
        public MotivoNota $motivo,
        public string $descripcionMotivo,
    ) {}

    public function numero(): string
    {
        return "{$this->serie}-{$this->correlativo}";
    }
}
