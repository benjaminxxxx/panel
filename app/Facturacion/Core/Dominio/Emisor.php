<?php

namespace App\Facturacion\Core\Dominio;

final readonly class Emisor
{
    public function __construct(
        public string $ruc,
        public string $razonSocial,
        public ?string $nombreComercial,
        public string $direccion,
        public string $ubigeo,
        public string $departamento,
        public string $provincia,
        public string $distrito,
        public ?string $urbanizacion = null,
        /** Código de establecimiento anexo asignado por SUNAT; '0000' = domicilio fiscal. */
        public string $codigoLocal = '0000',
    ) {}
}
