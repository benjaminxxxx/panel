<?php

namespace App\Facturacion;

use App\Models\Comprobante;

final readonly class ResultadoEmision
{
    public function __construct(
        public Comprobante $comprobante,
        /** false = ya existía un comprobante con esa referencia_externa (reintento idempotente). */
        public bool $nuevo,
    ) {}
}
