<?php

namespace App\Facturacion\Core\Dominio;

use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;

final readonly class Cliente
{
    public function __construct(
        public TipoDocumentoIdentidad $tipoDocumento,
        public string $numeroDocumento,
        public string $razonSocial,
        public ?string $direccion = null,
        public ?string $email = null,
    ) {}

    public static function varios(): self
    {
        return new self(TipoDocumentoIdentidad::Varios, '-', 'CLIENTES VARIOS');
    }
}
