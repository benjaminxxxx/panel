<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Sunat\ClasificadorRespuesta;
use PHPUnit\Framework\TestCase;

class ClasificadorRespuestaTest extends TestCase
{
    public function test_cdr(): void
    {
        $this->assertSame(EstadoComprobante::Aceptado, ClasificadorRespuesta::desdeCdr('0', []));
        $this->assertSame(EstadoComprobante::Observado, ClasificadorRespuesta::desdeCdr('0', ['4252 - observación']));
        $this->assertSame(EstadoComprobante::Observado, ClasificadorRespuesta::desdeCdr('4252', []));
        $this->assertSame(EstadoComprobante::Rechazado, ClasificadorRespuesta::desdeCdr('2800', []));
        $this->assertSame(EstadoComprobante::Rechazado, ClasificadorRespuesta::desdeCdr('3999', []));
        $this->assertSame(EstadoComprobante::ErrorEnvio, ClasificadorRespuesta::desdeCdr('0109', []));
    }

    public function test_soap_fault(): void
    {
        // Un rechazo que llega como fault NO es un error de comunicación.
        $this->assertSame(EstadoComprobante::Rechazado, ClasificadorRespuesta::desdeError('2800'));
        $this->assertSame(EstadoComprobante::ErrorEnvio, ClasificadorRespuesta::desdeError('0111'));
        $this->assertSame(EstadoComprobante::ErrorEnvio, ClasificadorRespuesta::desdeError('1033'));
        $this->assertSame(EstadoComprobante::ErrorEnvio, ClasificadorRespuesta::desdeError('HTTP'));
        $this->assertSame(EstadoComprobante::ErrorEnvio, ClasificadorRespuesta::desdeError(null));
    }
}
