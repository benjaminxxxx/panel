<?php

namespace App\Facturacion\Core\Sunat;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use SensitiveParameter;

final readonly class Credenciales
{
    /** Usuario/clave SOL que SUNAT acepta en beta para cualquier RUC. */
    public const USUARIO_BETA = 'MODDATOS';

    public const CLAVE_BETA = 'moddatos';

    public function __construct(
        public string $ruc,
        public string $usuarioSol,
        #[SensitiveParameter] public string $claveSol,
        public CertificadoDigital $certificado,
        public Entorno $entorno,
    ) {}

    public function __debugInfo(): array
    {
        return ['ruc' => $this->ruc, 'usuarioSol' => $this->usuarioSol, 'entorno' => $this->entorno];
    }
}
