<?php

namespace Tests\Integration;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Dominio\Cliente;
use App\Facturacion\Core\Dominio\DocumentoAfectado;
use App\Facturacion\Core\Dominio\DocumentoElectronico;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Enums\MotivoNotaCredito;
use App\Facturacion\Core\Enums\MotivoNotaDebito;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;
use App\Facturacion\Core\PreparadorComprobante;
use App\Facturacion\Core\Sunat\Credenciales;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Sunat\Greenter\GreenterServicioSunat;
use App\Facturacion\Core\Sunat\ResultadoEnvio;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\Facturacion;

/**
 * Envía comprobantes reales al servicio BETA de SUNAT.
 *
 * Ejecutar: SUNAT_BETA=1 php vendor/bin/phpunit --testsuite Integration
 * Opcional: SUNAT_CERT=ruta/al/certificado.pfx SUNAT_CERT_CLAVE=... para probar con un certificado real.
 */
#[Group('sunat-beta')]
class SunatBetaTest extends TestCase
{
    private static ?Credenciales $credenciales = null;

    protected function setUp(): void
    {
        if (! getenv('SUNAT_BETA')) {
            $this->markTestSkipped('Defina SUNAT_BETA=1 para enviar a SUNAT beta.');
        }

        self::$credenciales ??= new Credenciales(
            Facturacion::RUC_EMISOR_BETA,
            Credenciales::USUARIO_BETA,
            Credenciales::CLAVE_BETA,
            $this->certificado(),
            Entorno::Beta,
        );
    }

    private function certificado(): CertificadoDigital
    {
        $ruta = getenv('SUNAT_CERT');
        if ($ruta) {
            return CertificadoDigital::cargar((string) file_get_contents($ruta), getenv('SUNAT_CERT_CLAVE') ?: null);
        }

        return CertificadoDigital::cargar(Facturacion::certificadoAutofirmado()['pem']);
    }

    private function emitir(SolicitudComprobante $solicitud): ResultadoEnvio
    {
        $preparador = new PreparadorComprobante;
        $calculo = $preparador->preparar($solicitud, Facturacion::ahora());
        $documento = $preparador->documento(Facturacion::emisor(), $solicitud, random_int(1, 99_999_999), $calculo);

        $servicio = new GreenterServicioSunat;
        $xml = $servicio->firmar($documento, self::$credenciales);

        $this->assertNotSame('', $xml->hash);
        $this->assertStringContainsString('<ds:Signature', $xml->contenido);

        // Beta limita la frecuencia de envíos y responde "HTTP Unauthorized" de forma intermitente.
        for ($intento = 1; $intento <= 3; $intento++) {
            $resultado = $servicio->enviar($xml, self::$credenciales);
            if ($resultado->estado !== EstadoComprobante::ErrorEnvio) {
                break;
            }
            sleep(2 * $intento);
        }

        fwrite(STDERR, sprintf("\n  %s → %s [%s] %s", $this->numero($documento), $resultado->estado->value, $resultado->codigo, $resultado->descripcion));

        return $resultado;
    }

    private function numero(DocumentoElectronico $documento): string
    {
        return $documento->nombre();
    }

    public function test_factura_aceptada(): void
    {
        $resultado = $this->emitir(Facturacion::solicitud());

        $this->assertSame(EstadoComprobante::Aceptado, $resultado->estado, $resultado->descripcion);
        $this->assertSame('0', $resultado->codigo);
        $this->assertNotEmpty($resultado->cdrZip);
    }

    public function test_nota_de_credito_de_factura_y_de_boleta_aceptadas(): void
    {
        foreach ([[TipoComprobante::Factura, 'F001', 'FC01'], [TipoComprobante::Boleta, 'B001', 'BC01']] as [$tipo, $serieAfectada, $serieNota]) {
            $base = Facturacion::solicitud($tipo);

            $resultado = $this->emitir(new SolicitudComprobante(
                tipo: TipoComprobante::NotaCredito,
                serie: $serieNota,
                fechaEmision: $base->fechaEmision,
                cliente: $base->cliente,
                items: $base->items,
                afectado: new DocumentoAfectado($tipo, $serieAfectada, 1, MotivoNotaCredito::AnulacionOperacion, 'Anulación de la operación'),
            ));

            $this->assertSame(EstadoComprobante::Aceptado, $resultado->estado, $resultado->descripcion);
        }
    }

    public function test_nota_de_debito_y_nota_de_credito_parcial_aceptadas(): void
    {
        $notas = [
            [TipoComprobante::NotaDebito, TipoComprobante::Factura, 'F001', 'FD01', MotivoNotaDebito::AumentoValor],
            [TipoComprobante::NotaDebito, TipoComprobante::Boleta, 'B001', 'BD01', MotivoNotaDebito::AumentoValor],
            [TipoComprobante::NotaCredito, TipoComprobante::Factura, 'F001', 'FC01', MotivoNotaCredito::DisminucionValor],
            [TipoComprobante::NotaCredito, TipoComprobante::Boleta, 'B001', 'BC01', MotivoNotaCredito::DisminucionValor],
            [TipoComprobante::NotaCredito, TipoComprobante::Factura, 'F001', 'FC01', MotivoNotaCredito::DescuentoGlobal],
            [TipoComprobante::NotaCredito, TipoComprobante::Boleta, 'B001', 'BC01', MotivoNotaCredito::DevolucionPorItem],
        ];

        foreach ($notas as [$tipoNota, $tipo, $serieAfectada, $serieNota, $motivo]) {
            $base = Facturacion::solicitud($tipo);

            $resultado = $this->emitir(new SolicitudComprobante(
                tipo: $tipoNota,
                serie: $serieNota,
                fechaEmision: $base->fechaEmision,
                cliente: $base->cliente,
                items: [new ItemSolicitud(descripcion: mb_strtoupper($motivo->descripcion()), cantidad: '1', importe: '23.60')],
                afectado: new DocumentoAfectado($tipo, $serieAfectada, 1, $motivo, $motivo->descripcion()),
            ));

            $this->assertSame(EstadoComprobante::Aceptado, $resultado->estado, "{$tipoNota->nombre()} {$motivo->descripcion()} de {$tipo->nombre()}: {$resultado->descripcion}");
        }
    }

    public function test_boleta_clientes_varios_aceptada(): void
    {
        $resultado = $this->emitir(Facturacion::solicitud(TipoComprobante::Boleta));

        $this->assertSame(EstadoComprobante::Aceptado, $resultado->estado, $resultado->descripcion);
    }

    public function test_boleta_con_dni_y_cantidades_decimales(): void
    {
        $items = [
            new ItemSolicitud('CABLE THW 14 AWG', '12.5', 'MTR', 'CAB14', precioUnitario: '1.90'),
            new ItemSolicitud('CLAVOS 2"', '0.75', 'KGM', 'CLV2', precioUnitario: '8.50'),
            new ItemSolicitud('SERVICIO DE CORTE', '1', 'ZZ', valorUnitario: '4.2373'),
        ];
        $cliente = new Cliente(TipoDocumentoIdentidad::Dni, '45678912', 'JUAN PÉREZ & HIJOS');

        $resultado = $this->emitir(Facturacion::solicitud(TipoComprobante::Boleta, $cliente, $items));

        $this->assertSame(EstadoComprobante::Aceptado, $resultado->estado, $resultado->descripcion);
    }

    public function test_factura_con_muchos_items_y_precios_que_no_dividen_exacto(): void
    {
        mt_srand(42);
        $items = [];
        for ($i = 1; $i <= 60; $i++) {
            $items[] = new ItemSolicitud(
                "PRODUCTO {$i}",
                (string) mt_rand(1, 7),
                'NIU',
                "P{$i}",
                precioUnitario: number_format(mt_rand(10, 99999) / 100, 2, '.', ''),
            );
        }

        $resultado = $this->emitir(Facturacion::solicitud(items: $items));

        $this->assertSame(EstadoComprobante::Aceptado, $resultado->estado, $resultado->descripcion);
    }

    public function test_importes_fijados_que_no_dividen_exacto_son_aceptados(): void
    {
        $items = [
            new ItemSolicitud('MEDIAS X3', '3', importe: '100.00'),
            new ItemSolicitud('BOTONES', '7', importe: '10.00'),
            new ItemSolicitud('CLAVOS', '0.333', 'KGM', importe: '5.00'),
            new ItemSolicitud('BOLSA', '1', importe: '0.10'),
            new ItemSolicitud('POLO', '1', importe: '23.39'),
            new ItemSolicitud('CORBATA CON DESCUENTO', '2', importe: '63.90'),
        ];

        $resultado = $this->emitir(Facturacion::solicitud(items: $items));

        $this->assertSame(EstadoComprobante::Aceptado, $resultado->estado, $resultado->descripcion);
        $this->assertSame([], $resultado->notas, 'Sin observaciones de SUNAT');
    }

    public function test_rechazo_de_sunat_se_clasifica_como_rechazado(): void
    {
        // Saltamos el validador a propósito para forzar un rechazo real (factura a DNI → 2800).
        $solicitud = Facturacion::solicitud(TipoComprobante::Boleta, new Cliente(TipoDocumentoIdentidad::Dni, '45678912', 'JUAN'));
        $preparador = new PreparadorComprobante;
        $calculo = $preparador->preparar($solicitud, Facturacion::ahora());
        $boleta = $preparador->documento(Facturacion::emisor(), $solicitud, random_int(1, 99_999_999), $calculo);
        $factura = new DocumentoElectronico(
            $boleta->emisor, TipoComprobante::Factura, 'F001', $boleta->correlativo, $boleta->fechaEmision,
            $boleta->moneda, $boleta->cliente, $boleta->lineas, $boleta->totales, $boleta->montoEnLetras,
        );

        $servicio = new GreenterServicioSunat;
        $resultado = $servicio->enviar($servicio->firmar($factura, self::$credenciales), self::$credenciales);
        fwrite(STDERR, "\n  rechazo esperado → {$resultado->estado->value} [{$resultado->codigo}] {$resultado->descripcion}");

        $this->assertSame(EstadoComprobante::Rechazado, $resultado->estado);
        $this->assertSame('2800', $resultado->codigo);
    }
}
