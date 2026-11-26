<?php

namespace App\Facturacion\Core\Dominio;

final readonly class Totales
{
    public function __construct(
        public string $operacionesGravadas,
        public string $operacionesExoneradas,
        public string $operacionesInafectas,
        public string $igv,
        public string $totalImpuestos,
        /** Suma de valores de venta sin impuestos (LineExtensionAmount). */
        public string $valorVenta,
        /** valorVenta + impuestos (TaxInclusiveAmount). */
        public string $subTotal,
        /** Importe total a pagar (PayableAmount). */
        public string $importeTotal,
    ) {}
}
