<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Dominio\DocumentoAfectado;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Enums\MotivoNotaCredito;
use App\Facturacion\Core\Enums\MotivoNotaDebito;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\PreparadorComprobante;
use App\Facturacion\Core\Sunat\Credenciales;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Sunat\Greenter\GreenterServicioSunat;
use App\Facturacion\Core\Validacion\ComprobanteInvalido;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Tests\Support\Facturacion;

/**
 * El XML real (Greenter) de una nota de crédito, firmado con un certificado de prueba. No usa la red.
 */
class NotaCreditoXmlTest extends TestCase
{
    private function solicitud(TipoComprobante $tipoAfectado = TipoComprobante::Factura, string $serie = 'FC01'): SolicitudComprobante
    {
        $base = Facturacion::solicitud($tipoAfectado);

        return new SolicitudComprobante(
            tipo: TipoComprobante::NotaCredito,
            serie: $serie,
            fechaEmision: $base->fechaEmision,
            cliente: $base->cliente,
            items: $base->items,
            afectado: new DocumentoAfectado($tipoAfectado, $tipoAfectado === TipoComprobante::Factura ? 'F001' : 'B001', 7, MotivoNotaCredito::AnulacionOperacion, 'Anulación de la operación'),
        );
    }

    public function test_genera_un_credit_note_firmado_que_referencia_al_comprobante_modificado(): void
    {
        $solicitud = $this->solicitud();
        $preparador = new PreparadorComprobante;
        $documento = $preparador->documento(Facturacion::emisor(), $solicitud, 3, $preparador->preparar($solicitud, Facturacion::ahora()));

        $credenciales = new Credenciales(
            Facturacion::RUC_EMISOR_BETA,
            Credenciales::USUARIO_BETA,
            Credenciales::CLAVE_BETA,
            CertificadoDigital::cargar(Facturacion::certificadoAutofirmado()['pem']),
            Entorno::Beta,
        );

        $xml = (new GreenterServicioSunat)->firmar($documento, $credenciales);

        $dom = new DOMDocument;
        $dom->loadXML($xml->contenido);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $valor = fn (string $ruta) => $xpath->query($ruta)->item(0)?->nodeValue;

        $this->assertSame('20000000001-07-FC01-3', $xml->nombre);
        $this->assertNotSame('', $xml->hash);
        $this->assertSame('CreditNote', $dom->documentElement->localName);
        $this->assertSame('FC01-3', $valor('/*/cbc:ID'));
        $this->assertSame('F001-7', $valor('//cac:DiscrepancyResponse/cbc:ReferenceID'));
        $this->assertSame('01', $valor('//cac:DiscrepancyResponse/cbc:ResponseCode'));
        $this->assertSame('Anulación de la operación', $valor('//cac:DiscrepancyResponse/cbc:Description'));
        $this->assertSame('F001-7', $valor('//cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'));
        $this->assertSame('01', $valor('//cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode'));
        $this->assertSame('118.00', $valor('//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        $this->assertSame(1, $xpath->query('//cac:CreditNoteLine')->length);
    }

    public function test_una_nota_de_debito_genera_un_debit_note_con_su_motivo(): void
    {
        $base = Facturacion::solicitud();
        $solicitud = new SolicitudComprobante(
            tipo: TipoComprobante::NotaDebito,
            serie: 'FD01',
            fechaEmision: $base->fechaEmision,
            cliente: $base->cliente,
            items: $base->items,
            afectado: new DocumentoAfectado(TipoComprobante::Factura, 'F001', 7, MotivoNotaDebito::AumentoValor, 'Aumento en el valor'),
        );
        $preparador = new PreparadorComprobante;
        $documento = $preparador->documento(Facturacion::emisor(), $solicitud, 3, $preparador->preparar($solicitud, Facturacion::ahora()));

        $xml = (new GreenterServicioSunat)->firmar($documento, new Credenciales(
            Facturacion::RUC_EMISOR_BETA,
            Credenciales::USUARIO_BETA,
            Credenciales::CLAVE_BETA,
            CertificadoDigital::cargar(Facturacion::certificadoAutofirmado()['pem']),
            Entorno::Beta,
        ));

        $dom = new DOMDocument;
        $dom->loadXML($xml->contenido);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $valor = fn (string $ruta) => $xpath->query($ruta)->item(0)?->nodeValue;

        $this->assertSame('20000000001-08-FD01-3', $xml->nombre);
        $this->assertSame('DebitNote', $dom->documentElement->localName);
        $this->assertSame('F001-7', $valor('//cac:DiscrepancyResponse/cbc:ReferenceID'));
        $this->assertSame('02', $valor('//cac:DiscrepancyResponse/cbc:ResponseCode'));
        $this->assertSame('118.00', $valor('//cac:RequestedMonetaryTotal/cbc:PayableAmount'));
        $this->assertSame(1, $xpath->query('//cac:DebitNoteLine')->length);
    }

    public function test_la_serie_de_la_nota_debe_empezar_como_la_del_comprobante_que_modifica(): void
    {
        $preparador = new PreparadorComprobante;

        $preparador->preparar($this->solicitud(TipoComprobante::Boleta, 'BC01'), Facturacion::ahora());

        try {
            $preparador->preparar($this->solicitud(TipoComprobante::Boleta, 'FC01'), Facturacion::ahora());
            $this->fail('Una nota de crédito de boleta no puede usar una serie que empieza con F.');
        } catch (ComprobanteInvalido $e) {
            $this->assertArrayHasKey('serie', $e->errores);
        }
    }

    public function test_una_nota_de_credito_sin_comprobante_modificado_es_invalida(): void
    {
        $base = Facturacion::solicitud();
        $solicitud = new SolicitudComprobante(TipoComprobante::NotaCredito, 'FC01', $base->fechaEmision, $base->cliente, $base->items);

        $this->expectException(ComprobanteInvalido::class);

        (new PreparadorComprobante)->preparar($solicitud, Facturacion::ahora());
    }
}
