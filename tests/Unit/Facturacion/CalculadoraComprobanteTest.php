<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Calculo\CalculadoraComprobante;
use App\Facturacion\Core\Calculo\Decimal;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Facturacion;

class CalculadoraComprobanteTest extends TestCase
{
    /**
     * @return array<string, array{ItemSolicitud, array<string, string>}>
     */
    public static function lineas(): array
    {
        return [
            'ejemplo greenter 2 x 59.00' => [
                new ItemSolicitud('P', '2', precioUnitario: '59.00'),
                ['valorVenta' => '100.00', 'igv' => '18.00', 'importeTotal' => '118.00', 'valorUnitario' => '50', 'precioUnitario' => '59'],
            ],
            'precio que no divide exacto' => [
                new ItemSolicitud('P', '1', precioUnitario: '10.00'),
                ['valorVenta' => '8.47', 'igv' => '1.53', 'importeTotal' => '10.00', 'valorUnitario' => '8.47'],
            ],
            'céntimos (ruido float 0.1 * 3)' => [
                new ItemSolicitud('P', '3', precioUnitario: '0.10'),
                ['valorVenta' => '0.25', 'igv' => '0.05', 'importeTotal' => '0.30'],
            ],
            'cantidad decimal (1.5 m de cable)' => [
                new ItemSolicitud('Cable', '1.5', 'MTR', precioUnitario: '12.90'),
                ['valorVenta' => '16.40', 'igv' => '2.95', 'importeTotal' => '19.35', 'valorUnitario' => '10.9333333333', 'cantidad' => '1.5'],
            ],
            'valor unitario sin IGV' => [
                new ItemSolicitud('Servicio', '2', 'ZZ', valorUnitario: '50'),
                ['valorVenta' => '100.00', 'igv' => '18.00', 'importeTotal' => '118.00', 'precioUnitario' => '59'],
            ],
            'valor unitario con redondeo half-up' => [
                new ItemSolicitud('P', '1', valorUnitario: '8.475'),
                ['valorVenta' => '8.48', 'igv' => '1.53', 'importeTotal' => '10.01'],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $esperado
     */
    #[DataProvider('lineas')]
    public function test_calcula_linea(ItemSolicitud $item, array $esperado): void
    {
        $resultado = (new CalculadoraComprobante)->calcular(Facturacion::solicitud(items: [$item]));
        $linea = $resultado->lineas[0];

        foreach ($esperado as $campo => $valor) {
            $this->assertSame($valor, $linea->{$campo}, "Campo {$campo}");
        }

        $this->assertSame('10', $linea->tipoAfectacionIgv);
        $this->assertSame($linea->valorVenta, $linea->baseIgv);
    }

    public function test_totales_son_la_suma_exacta_de_las_lineas(): void
    {
        $items = [
            new ItemSolicitud('A', '1', precioUnitario: '10.00'),
            new ItemSolicitud('B', '1', precioUnitario: '10.00'),
            new ItemSolicitud('C', '1', precioUnitario: '10.00'),
        ];

        $totales = (new CalculadoraComprobante)->calcular(Facturacion::solicitud(items: $items))->totales;

        // Lo que cobró la caja es exactamente lo que dice el comprobante.
        $this->assertSame('30.00', $totales->importeTotal);
        $this->assertSame('25.41', $totales->operacionesGravadas);
        $this->assertSame('4.59', $totales->igv);
        $this->assertSame('25.41', $totales->valorVenta);
        $this->assertSame('30.00', $totales->subTotal);
        $this->assertSame('4.59', $totales->totalImpuestos);
    }

    public function test_propiedades_se_cumplen_con_precios_aleatorios(): void
    {
        mt_srand(20260930);
        $items = [];
        for ($i = 0; $i < 200; $i++) {
            $cantidad = (string) (mt_rand(1, 5000) / (mt_rand(0, 1) ? 1 : 100));
            $precio = number_format(mt_rand(1, 999999) / 100, 2, '.', '');
            $items[] = new ItemSolicitud("Item {$i}", $cantidad, precioUnitario: $precio);
        }

        $resultado = (new CalculadoraComprobante)->calcular(Facturacion::solicitud(items: $items));
        $sumaImportes = '0';

        foreach ($resultado->lineas as $linea) {
            // valor + IGV = importe de la línea, al céntimo.
            $this->assertSame($linea->importeTotal, Decimal::redondear(Decimal::sumar($linea->valorVenta, $linea->igv), 2));

            // IGV dentro de la tolerancia SUNAT (±1), en la práctica < 1 céntimo.
            $igvTeorico = Decimal::multiplicar($linea->baseIgv, '0.18');
            $this->assertLessThanOrEqual(0.01, abs((float) Decimal::restar($linea->igv, $igvTeorico)));

            // cantidad x valor unitario ≈ valor de venta (regla 4288, tolerancia ±1).
            $producto = Decimal::multiplicar($linea->cantidad, $linea->valorUnitario);
            $this->assertLessThanOrEqual(0.01, abs((float) Decimal::restar($producto, $linea->valorVenta)));

            $sumaImportes = Decimal::sumar($sumaImportes, $linea->importeTotal);
        }

        $this->assertSame(Decimal::redondear($sumaImportes, 2), $resultado->totales->importeTotal);
        $this->assertLessThanOrEqual(1.0, abs((float) Decimal::restar(
            $resultado->totales->igv,
            Decimal::multiplicar($resultado->totales->operacionesGravadas, '0.18'),
        )));
    }
}
