<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Calculo\CalculadoraComprobante;
use App\Facturacion\Core\Calculo\Decimal;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\LineaCalculada;
use App\Facturacion\Core\Dominio\ResultadoCalculo;
use App\Facturacion\Core\PreparadorComprobante;
use App\Facturacion\Core\Validacion\ComprobanteInvalido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Facturacion;

/**
 * Casos reales de caja: el sistema de venta fija el monto final de cada línea (con IGV) y el
 * comprobante debe sumar exactamente eso, sin "monto de redondeo".
 *
 * Cada caso además verifica las reglas que SUNAT valida sobre las líneas.
 */
class CasosDePrecioTest extends TestCase
{
    /**
     * @param  list<ItemSolicitud>  $items
     */
    private function calcular(array $items): ResultadoCalculo
    {
        return (new CalculadoraComprobante)->calcular(Facturacion::solicitud(items: $items));
    }

    private function assertCumpleReglasSunat(LineaCalculada $l): void
    {
        // Valor de venta + IGV = importe de la línea, al céntimo.
        $this->assertSame($l->importeTotal, Decimal::redondear(Decimal::sumar($l->valorVenta, $l->igv), 2), 'valor + IGV = importe');

        // IGV = base × 18 % (SUNAT tolera ±1; aquí exigimos 1 céntimo).
        $this->assertLessThanOrEqual(0.01, abs((float) Decimal::restar($l->igv, Decimal::multiplicar($l->baseIgv, '0.18'))), 'IGV vs base × 18%');

        // cantidad × valor unitario = valor de venta (regla 4288).
        $this->assertLessThanOrEqual(0.01, abs((float) Decimal::restar(Decimal::multiplicar($l->cantidad, $l->valorUnitario), $l->valorVenta)), 'cantidad × valor unitario');

        // cantidad × precio unitario vuelve al importe (lo que se imprime cuadra).
        $this->assertSame($l->importeTotal, Decimal::redondear(Decimal::multiplicar($l->cantidad, $l->precioUnitario), 2), 'cantidad × precio unitario');

        // Formatos del XML: montos con 2 decimales, unitarios con máximo 10.
        $this->assertLessThanOrEqual(10, Decimal::cantidadDecimales($l->precioUnitario));
        $this->assertLessThanOrEqual(10, Decimal::cantidadDecimales($l->valorUnitario));
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $l->importeTotal);
    }

    /**
     * @return array<string, array{ItemSolicitud, array<string, string>}>
     */
    public static function montosFijados(): array
    {
        return [
            'precio "no real" 23.39' => [
                new ItemSolicitud('Polo', '1', importe: '23.39'),
                ['importeTotal' => '23.39', 'valorVenta' => '19.82', 'igv' => '3.57', 'precioUnitario' => '23.39'],
            ],
            '3 unidades por S/ 100.00' => [
                new ItemSolicitud('Medias x3', '3', importe: '100.00'),
                ['importeTotal' => '100.00', 'valorVenta' => '84.75', 'igv' => '15.25', 'precioUnitario' => '33.3333333333'],
            ],
            '7 unidades por S/ 10.00' => [
                new ItemSolicitud('Botones', '7', importe: '10'),
                ['importeTotal' => '10.00', 'valorVenta' => '8.47', 'igv' => '1.53', 'precioUnitario' => '1.4285714286'],
            ],
            '0.333 kg por S/ 5.00' => [
                new ItemSolicitud('Clavos', '0.333', 'KGM', importe: '5.00'),
                ['importeTotal' => '5.00', 'valorVenta' => '4.24', 'igv' => '0.76', 'precioUnitario' => '15.015015015'],
            ],
            'descuento 10 %: 2 × 35.50 = 63.90' => [
                new ItemSolicitud('Corbata', '2', importe: '63.90'),
                ['importeTotal' => '63.90', 'valorVenta' => '54.15', 'igv' => '9.75', 'precioUnitario' => '31.95'],
            ],
            'S/ 0.10: no se recupera desde el neto (0.08 × 1.18 = 0.09)' => [
                new ItemSolicitud('Bolsa', '1', importe: '0.10'),
                ['importeTotal' => '0.10', 'valorVenta' => '0.08', 'igv' => '0.02'],
            ],
            'S/ 0.01' => [
                new ItemSolicitud('Ajuste', '1', importe: '0.01'),
                ['importeTotal' => '0.01', 'valorVenta' => '0.01', 'igv' => '0.00'],
            ],
            'terno S/ 1,299.90' => [
                new ItemSolicitud('Terno', '1', importe: '1299.90'),
                ['importeTotal' => '1299.90', 'valorVenta' => '1101.61', 'igv' => '198.29'],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $esperado
     */
    #[DataProvider('montosFijados')]
    public function test_el_importe_fijado_se_respeta_al_centimo(ItemSolicitud $item, array $esperado): void
    {
        $resultado = $this->calcular([$item]);
        $linea = $resultado->lineas[0];

        foreach ($esperado as $campo => $valor) {
            $this->assertSame($valor, $linea->{$campo}, $campo);
        }

        $this->assertCumpleReglasSunat($linea);
        $this->assertSame($linea->importeTotal, $resultado->totales->importeTotal);
    }

    public function test_venta_de_varias_lineas_suma_exactamente_lo_cobrado(): void
    {
        // Misma venta que se emitió en SUNAT beta desde trajes: terno 349.90 + 2 corbatas con 10 % (63.90).
        $resultado = $this->calcular([
            new ItemSolicitud('Terno', '1', importe: '349.90'),
            new ItemSolicitud('Corbata', '2', importe: '63.90'),
        ]);

        $this->assertSame('413.80', $resultado->totales->importeTotal);
        $this->assertSame('350.68', $resultado->totales->operacionesGravadas);
        $this->assertSame('63.12', $resultado->totales->igv);

        foreach ($resultado->lineas as $linea) {
            $this->assertCumpleReglasSunat($linea);
        }
    }

    public function test_con_precio_unitario_redondeado_no_se_llega_al_monto_fijado(): void
    {
        // Por qué existe "importe": 3 × 33.33 = 99.99, no 100.00. El portal de SUNAT lo "arregla" con redondeo;
        // aquí basta enviar el importe (o el precio con más decimales).
        $conPrecio = $this->calcular([new ItemSolicitud('Medias x3', '3', precioUnitario: '33.33')]);
        $conImporte = $this->calcular([new ItemSolicitud('Medias x3', '3', importe: '100.00')]);
        $conPrecioLargo = $this->calcular([new ItemSolicitud('Medias x3', '3', precioUnitario: '33.3333333333')]);

        $this->assertSame('99.99', $conPrecio->totales->importeTotal);
        $this->assertSame('100.00', $conImporte->totales->importeTotal);
        $this->assertSame('100.00', $conPrecioLargo->totales->importeTotal);
    }

    public function test_los_tres_modos_coinciden_cuando_el_precio_es_exacto(): void
    {
        $importe = $this->calcular([new ItemSolicitud('P', '2', importe: '118.00')])->lineas[0];
        $precio = $this->calcular([new ItemSolicitud('P', '2', precioUnitario: '59')])->lineas[0];
        $valor = $this->calcular([new ItemSolicitud('P', '2', valorUnitario: '50')])->lineas[0];

        foreach (['importeTotal', 'valorVenta', 'igv', 'valorUnitario', 'precioUnitario'] as $campo) {
            $this->assertSame($precio->{$campo}, $importe->{$campo}, $campo);
            $this->assertSame($precio->{$campo}, $valor->{$campo}, $campo);
        }
    }

    public function test_propiedad_cualquier_importe_y_cantidad_cumple_las_reglas(): void
    {
        mt_srand(23_39);
        $items = [];
        for ($i = 0; $i < 400; $i++) {
            $cantidad = mt_rand(0, 3) === 0 ? number_format(mt_rand(1, 99999) / 1000, 3, '.', '') : (string) mt_rand(1, 60);
            $items[] = new ItemSolicitud("Item {$i}", $cantidad, importe: number_format(mt_rand(1, 500000) / 100, 2, '.', ''));
        }

        $resultado = $this->calcular($items);
        $suma = '0';

        foreach ($resultado->lineas as $i => $linea) {
            $this->assertSame(Decimal::redondear(Decimal::normalizar((string) $items[$i]->importe), 2), $linea->importeTotal);
            $this->assertCumpleReglasSunat($linea);
            $suma = Decimal::sumar($suma, $linea->importeTotal);
        }

        $this->assertSame(Decimal::redondear($suma, 2), $resultado->totales->importeTotal);
    }

    public function test_validaciones_del_importe(): void
    {
        $errores = function (ItemSolicitud $item): array {
            try {
                (new PreparadorComprobante)->preparar(Facturacion::solicitud(items: [$item]), Facturacion::ahora());
            } catch (ComprobanteInvalido $e) {
                return $e->errores;
            }

            return [];
        };

        $this->assertArrayHasKey('items.0.importe', $errores(new ItemSolicitud('P', '1', importe: '10.005')), 'más de 2 decimales');
        $this->assertArrayHasKey('items.0.importe', $errores(new ItemSolicitud('P', '1', importe: '0')), 'cero');
        $this->assertArrayHasKey('items.0.precio_unitario', $errores(new ItemSolicitud('P', '1', precioUnitario: '10', importe: '10')), 'dos formas de precio');
        $this->assertSame([], $errores(new ItemSolicitud('P', '1', importe: '10.50')));
    }
}
