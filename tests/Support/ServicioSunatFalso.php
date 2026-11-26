<?php

namespace Tests\Support;

use App\Facturacion\Core\Dominio\DocumentoElectronico;
use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Sunat\Credenciales;
use App\Facturacion\Core\Sunat\ResultadoEnvio;
use App\Facturacion\Core\Sunat\ServicioSunat;
use App\Facturacion\Core\Sunat\XmlFirmado;
use RuntimeException;

final class ServicioSunatFalso implements ServicioSunat
{
    public ResultadoEnvio $respuesta;

    public int $envios = 0;

    public bool $fallarFirma = false;

    /** @var list<DocumentoElectronico> */
    public array $firmados = [];

    public function __construct()
    {
        $this->aceptar();
    }

    public function aceptar(): self
    {
        $this->respuesta = new ResultadoEnvio(EstadoComprobante::Aceptado, '0', 'La Factura ha sido aceptada', [], 'CDR-ZIP');

        return $this;
    }

    public function responder(EstadoComprobante $estado, string $codigo, string $descripcion): self
    {
        $this->respuesta = new ResultadoEnvio($estado, $codigo, $descripcion);

        return $this;
    }

    public function firmar(DocumentoElectronico $documento, Credenciales $credenciales): XmlFirmado
    {
        if ($this->fallarFirma) {
            throw new RuntimeException('Certificado corrupto');
        }

        $this->firmados[] = $documento;

        return new XmlFirmado($documento->nombre(), "<Invoice>{$documento->nombre()}</Invoice>", 'HASH-'.$documento->correlativo);
    }

    public function enviar(XmlFirmado $xml, Credenciales $credenciales): ResultadoEnvio
    {
        $this->envios++;

        return $this->respuesta;
    }
}
