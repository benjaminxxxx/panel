<?php

namespace App\Facturacion\Core\Sunat;

enum Entorno: string
{
    case Beta = 'beta';
    case Produccion = 'produccion';

    public function urlFacturas(): string
    {
        return match ($this) {
            self::Beta => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
            self::Produccion => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
        };
    }
}
