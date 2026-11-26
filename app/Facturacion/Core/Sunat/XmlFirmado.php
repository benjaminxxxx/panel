<?php

namespace App\Facturacion\Core\Sunat;

final readonly class XmlFirmado
{
    public function __construct(
        /** RUC-TIPO-SERIE-CORRELATIVO, sin extensión. */
        public string $nombre,
        public string $contenido,
        /** DigestValue de la firma: va impreso en la representación impresa / QR. */
        public string $hash,
    ) {}
}
