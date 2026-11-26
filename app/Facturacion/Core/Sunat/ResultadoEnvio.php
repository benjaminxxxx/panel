<?php

namespace App\Facturacion\Core\Sunat;

use App\Facturacion\Core\Enums\EstadoComprobante;

final readonly class ResultadoEnvio
{
    /**
     * @param  list<string>  $notas  Observaciones del CDR.
     */
    public function __construct(
        public EstadoComprobante $estado,
        public ?string $codigo,
        public string $descripcion,
        public array $notas = [],
        /** ZIP del CDR (binario) cuando SUNAT lo devuelve. */
        public ?string $cdrZip = null,
    ) {}
}
