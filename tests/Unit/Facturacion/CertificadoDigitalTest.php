<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Certificado\CertificadoInvalido;
use PHPUnit\Framework\TestCase;
use Tests\Support\Facturacion;

class CertificadoDigitalTest extends TestCase
{
    public function test_carga_pem(): void
    {
        $cert = CertificadoDigital::cargar(Facturacion::certificadoAutofirmado()['pem']);

        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert->pem());
        $this->assertStringContainsString('PRIVATE KEY', $cert->pem());
        $this->assertSame('20000000001', $cert->rucEnSujeto());
        $this->assertTrue($cert->estaVigente(Facturacion::ahora()));
        $this->assertGreaterThan(360, $cert->diasParaVencer(Facturacion::ahora()));
    }

    public function test_carga_pfx_con_clave(): void
    {
        $generado = Facturacion::certificadoAutofirmado();
        $cert = CertificadoDigital::cargar($generado['pfx'], $generado['clave']);

        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert->pem());
    }

    public function test_pfx_con_clave_incorrecta(): void
    {
        $this->expectException(CertificadoInvalido::class);
        $this->expectExceptionMessage('contraseña');

        CertificadoDigital::cargar(Facturacion::certificadoAutofirmado()['pfx'], 'otra');
    }

    public function test_clave_privada_de_otro_certificado(): void
    {
        $a = Facturacion::certificadoAutofirmado()['pem'];
        $b = Facturacion::certificadoAutofirmado()['pem'];
        preg_match('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $a, $certA);
        preg_match('/-----BEGIN PRIVATE KEY-----.+?-----END PRIVATE KEY-----/s', $b, $llaveB);

        $this->expectException(CertificadoInvalido::class);
        $this->expectExceptionMessage('no corresponde');

        CertificadoDigital::cargar($certA[0]."\n".$llaveB[0]);
    }

    public function test_pem_sin_clave_privada(): void
    {
        preg_match('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', Facturacion::certificadoAutofirmado()['pem'], $cert);

        $this->expectException(CertificadoInvalido::class);
        CertificadoDigital::cargar($cert[0]);
    }
}
