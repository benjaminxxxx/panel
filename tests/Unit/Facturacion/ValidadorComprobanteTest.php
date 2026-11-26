<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Dominio\Cliente;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;
use App\Facturacion\Core\PreparadorComprobante;
use App\Facturacion\Core\Validacion\ComprobanteInvalido;
use PHPUnit\Framework\TestCase;
use Tests\Support\Facturacion;

class ValidadorComprobanteTest extends TestCase
{
    /**
     * @return array<string, list<string>>
     */
    private function errores(SolicitudComprobante $solicitud): array
    {
        try {
            (new PreparadorComprobante)->preparar($solicitud, Facturacion::ahora());
        } catch (ComprobanteInvalido $e) {
            return $e->errores;
        }

        return [];
    }

    public function test_factura_valida_no_tiene_errores(): void
    {
        $this->assertSame([], $this->errores(Facturacion::solicitud()));
    }

    public function test_factura_exige_cliente_con_ruc(): void
    {
        $dni = new Cliente(TipoDocumentoIdentidad::Dni, '45678912', 'JUAN PEREZ');

        $this->assertArrayHasKey('cliente.tipo_documento', $this->errores(Facturacion::solicitud(cliente: $dni)));
        $this->assertArrayHasKey('cliente.tipo_documento', $this->errores(Facturacion::solicitud(cliente: Cliente::varios())));
    }

    public function test_factura_rechaza_ruc_con_digito_verificador_incorrecto(): void
    {
        $cliente = new Cliente(TipoDocumentoIdentidad::Ruc, '20100070971', 'X SAC');

        $this->assertArrayHasKey('cliente.numero_documento', $this->errores(Facturacion::solicitud(cliente: $cliente)));
    }

    public function test_serie_debe_corresponder_al_tipo(): void
    {
        $this->assertArrayHasKey('serie', $this->errores(Facturacion::solicitud(serie: 'B001')));
        $this->assertArrayHasKey('serie', $this->errores(Facturacion::solicitud(TipoComprobante::Boleta, serie: 'F001')));
        $this->assertArrayHasKey('serie', $this->errores(Facturacion::solicitud(serie: 'F01')));
        $this->assertSame([], $this->errores(Facturacion::solicitud(serie: 'FA01')));
    }

    public function test_plazo_de_emision(): void
    {
        $ahora = Facturacion::ahora();

        $this->assertSame([], $this->errores(Facturacion::solicitud(fecha: $ahora->modify('-3 days'))));
        $this->assertArrayHasKey('fecha_emision', $this->errores(Facturacion::solicitud(fecha: $ahora->modify('-4 days'))));
        $this->assertArrayHasKey('fecha_emision', $this->errores(Facturacion::solicitud(fecha: $ahora->modify('+1 day'))));
    }

    public function test_boleta_mayor_a_700_exige_cliente_identificado(): void
    {
        $item700 = [new ItemSolicitud('P', '1', precioUnitario: '700.00')];
        $item701 = [new ItemSolicitud('P', '1', precioUnitario: '700.01')];
        $dni = new Cliente(TipoDocumentoIdentidad::Dni, '45678912', 'JUAN PEREZ');

        $this->assertSame([], $this->errores(Facturacion::solicitud(TipoComprobante::Boleta, items: $item700)));
        $this->assertArrayHasKey('cliente', $this->errores(Facturacion::solicitud(TipoComprobante::Boleta, items: $item701)));
        $this->assertSame([], $this->errores(Facturacion::solicitud(TipoComprobante::Boleta, cliente: $dni, items: $item701)));
    }

    public function test_dni_generico_no_es_valido(): void
    {
        $cliente = new Cliente(TipoDocumentoIdentidad::Dni, '00000000', 'VARIOS');

        $this->assertArrayHasKey('cliente.numero_documento', $this->errores(Facturacion::solicitud(TipoComprobante::Boleta, cliente: $cliente)));
    }

    public function test_items(): void
    {
        $casos = [
            'items.0.precio_unitario' => new ItemSolicitud('P', '1', precioUnitario: '10', valorUnitario: '8'),
            'items.0.cantidad' => new ItemSolicitud('P', '0', precioUnitario: '10'),
            'items.0.unidad' => new ItemSolicitud('P', '1', 'XYZ', precioUnitario: '10'),
            'items.0.descripcion' => new ItemSolicitud('  ', '1', precioUnitario: '10'),
        ];

        foreach ($casos as $campo => $item) {
            $this->assertArrayHasKey($campo, $this->errores(Facturacion::solicitud(items: [$item])), $campo);
        }

        $sinPrecio = new ItemSolicitud('P', '1');
        $this->assertArrayHasKey('items.0.precio_unitario', $this->errores(Facturacion::solicitud(items: [$sinPrecio])));

        $muchosDecimales = new ItemSolicitud('P', '1.1234567', precioUnitario: '10');
        $this->assertArrayHasKey('items.0.cantidad', $this->errores(Facturacion::solicitud(items: [$muchosDecimales])));

        $this->assertArrayHasKey('items', $this->errores(Facturacion::solicitud(items: [])));
    }

    public function test_linea_que_redondea_a_cero(): void
    {
        $item = new ItemSolicitud('P', '1', precioUnitario: '0.001');

        $this->assertArrayHasKey('items.0', $this->errores(Facturacion::solicitud(items: [$item])));
    }

    public function test_solo_soles(): void
    {
        $base = Facturacion::solicitud();
        $usd = new SolicitudComprobante($base->tipo, $base->serie, $base->fechaEmision, $base->cliente, $base->items, 'USD');

        $this->assertArrayHasKey('moneda', $this->errores($usd));
    }
}
