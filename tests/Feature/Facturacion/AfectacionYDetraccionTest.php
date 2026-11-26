<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Core\Calculo\CalculadoraComprobante;
use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Dominio\Detraccion;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\PreparadorComprobante;
use App\Facturacion\Core\Sunat\Credenciales;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Sunat\Greenter\GreenterServicioSunat;
use App\Facturacion\Core\Validacion\ComprobanteInvalido;
use DOMDocument;
use DOMXPath;
use Tests\Support\Facturacion;
use Tests\TestCase;

/**
 * IGV por ítem (gravado 18 % / 10.5 %, exonerado, inafecto) y facturas sujetas a detracción.
 */
class AfectacionYDetraccionTest extends TestCase
{
    private function solicitud(array $items, TipoComprobante $tipo = TipoComprobante::Factura, ?Detraccion $detraccion = null): SolicitudComprobante
    {
        $base = Facturacion::solicitud($tipo);

        return new SolicitudComprobante(tipo: $tipo, serie: $base->serie, fechaEmision: $base->fechaEmision,
            cliente: $base->cliente, items: $items, detraccion: $detraccion);
    }

    public function test_venta_mixta_separa_gravado_exonerado_e_inafecto(): void
    {
        $calculo = (new CalculadoraComprobante)->calcular($this->solicitud([
            new ItemSolicitud('Pan y tortas', '1', importe: '11.80'),
            new ItemSolicitud('Huevos frescos (kg)', '1', 'KGM', importe: '8.50', afectacion: ItemSolicitud::EXONERADO),
            new ItemSolicitud('Bolsa', '1', importe: '0.50', afectacion: ItemSolicitud::INAFECTO),
        ]));
        $t = $calculo->totales;

        $this->assertSame(['10.00', '8.50', '0.50', '1.80', '19.00', '20.80'],
            [$t->operacionesGravadas, $t->operacionesExoneradas, $t->operacionesInafectas, $t->igv, $t->valorVenta, $t->importeTotal]);
        $this->assertSame('0', $calculo->lineas[1]->porcentajeIgv);
        $this->assertSame('0.00', $calculo->lineas[1]->igv);
    }

    public function test_tasa_especial_de_restaurantes_mype(): void
    {
        $linea = (new CalculadoraComprobante)->calcular($this->solicitud([
            new ItemSolicitud('Desayuno', '1', importe: '11.05', porcentajeIgv: '10.5'),
        ]))->lineas[0];

        $this->assertSame(['10.00', '1.05', '10.5'], [$linea->valorVenta, $linea->igv, $linea->porcentajeIgv]);
    }

    public function test_tasa_no_soportada_o_en_item_exonerado_se_rechaza(): void
    {
        foreach ([new ItemSolicitud('P', '1', importe: '10', porcentajeIgv: '16'),
            new ItemSolicitud('Huevos', '1', importe: '10', afectacion: ItemSolicitud::EXONERADO, porcentajeIgv: '18')] as $item) {
            try {
                (new PreparadorComprobante)->preparar($this->solicitud([$item]), Facturacion::ahora());
                $this->fail('debió rechazarse');
            } catch (ComprobanteInvalido $e) {
                $this->assertArrayHasKey('items.0.porcentaje_igv', $e->errores);
            }
        }
    }

    public function test_factura_con_detraccion_genera_operacion_1001_y_su_leyenda(): void
    {
        $solicitud = $this->solicitud([new ItemSolicitud('Panetones por encargo (insumos del cliente)', '100', importe: '1180.00')],
            detraccion: new Detraccion('025', '10', '00-123-456789'));
        $preparador = new PreparadorComprobante;
        $documento = $preparador->documento(Facturacion::emisor(), $solicitud, 9, $preparador->preparar($solicitud, Facturacion::ahora()));
        $credenciales = new Credenciales(Facturacion::RUC_EMISOR_BETA, Credenciales::USUARIO_BETA, Credenciales::CLAVE_BETA,
            CertificadoDigital::cargar(Facturacion::certificadoAutofirmado()['pem']), Entorno::Beta);

        $dom = new DOMDocument;
        $dom->loadXML((new GreenterServicioSunat)->firmar($documento, $credenciales)->contenido);
        $x = new DOMXPath($dom);
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $this->assertSame('1001', $x->query('//cbc:InvoiceTypeCode/@listID')->item(0)?->nodeValue);
        $this->assertSame(1, $x->query("//cbc:Note[@languageLocaleID='2006']")->length);
        $this->assertSame('025', $x->query("//cac:PaymentTerms[cbc:ID='Detraccion']/cbc:PaymentMeansID")->item(0)?->nodeValue);
        $this->assertSame('001', $x->query('//cac:PaymentMeans/cbc:PaymentMeansCode')->item(0)?->nodeValue);
        $this->assertSame('00-123-456789', $x->query('//cac:PayeeFinancialAccount/cbc:ID')->item(0)?->nodeValue);
        $this->assertSame('118.00', $x->query("//cac:PaymentTerms[cbc:ID='Detraccion']/cbc:Amount")->item(0)?->nodeValue);
    }

    public function test_detraccion_solo_en_facturas_mayores_a_700(): void
    {
        $this->expectException(ComprobanteInvalido::class);
        (new PreparadorComprobante)->preparar($this->solicitud([new ItemSolicitud('Pan', '1', importe: '500.00')],
            detraccion: new Detraccion('025', '10', '00123456789')), Facturacion::ahora());
    }
}
