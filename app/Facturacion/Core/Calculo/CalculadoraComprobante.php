<?php

namespace App\Facturacion\Core\Calculo;

use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\LineaCalculada;
use App\Facturacion\Core\Dominio\ResultadoCalculo;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Dominio\Totales;

/**
 * Calcula montos de línea y totales. Cada línea es gravada (catálogo 07, código 10, con su tasa:
 * 18 % general o 10.5 % restaurantes MYPE), exonerada (20) o inafecta (30, sin IGV).
 *
 * Criterio de redondeo: se redondea UNA vez por línea sobre el importe de la línea
 * (no sobre el unitario) y el IGV se obtiene por diferencia. Así, para precios con IGV,
 * valor de venta + IGV = importe cobrado exacto, y los totales son la suma exacta de las
 * líneas: no aparecen los descuadres de 1 céntimo entre cabecera y detalle.
 */
final class CalculadoraComprobante
{
    public const AFECTACION_GRAVADO_ONEROSO = '10';

    public function __construct(
        private readonly string $porcentajeIgv = '18',
    ) {}

    public function calcular(SolicitudComprobante $solicitud): ResultadoCalculo
    {
        $lineas = [];
        foreach ($solicitud->items as $indice => $item) {
            $lineas[] = $this->calcularLinea($indice + 1, $item);
        }

        return new ResultadoCalculo($lineas, $this->totalizar($lineas));
    }

    private function calcularLinea(int $orden, ItemSolicitud $item): LineaCalculada
    {
        $cantidad = Decimal::normalizar($item->cantidad);
        // Exonerado e inafecto: no llevan IGV; precio y valor son lo mismo.
        $porcentaje = $item->gravado() ? Decimal::normalizar($item->porcentajeIgv ?? $this->porcentajeIgv) : '0';
        $factor = Decimal::sumar('1', Decimal::dividir($porcentaje, '100'));

        if ($item->importe !== null || $item->precioUnitario !== null) {
            // Monto fijado de la línea (importe) o derivado de cantidad × precio. En ambos casos se parte
            // del total CON IGV y se calcula hacia atrás: así el comprobante suma exactamente lo cobrado
            // y no hace falta "monto de redondeo" (el precio unitario admite 10 decimales en el XML).
            $importeTotal = $item->importe !== null
                ? Decimal::redondear(Decimal::normalizar($item->importe), 2)
                : Decimal::redondear(Decimal::multiplicar($cantidad, Decimal::normalizar((string) $item->precioUnitario)), 2);
            $precioUnitario = $item->importe !== null
                ? Decimal::redondear(Decimal::dividir($importeTotal, $cantidad), 10)
                : Decimal::normalizar((string) $item->precioUnitario);
            $valorVenta = Decimal::redondear(Decimal::dividir($importeTotal, $factor), 2);
            $igv = Decimal::redondear(Decimal::restar($importeTotal, $valorVenta), 2);
            $valorUnitario = Decimal::redondear(Decimal::dividir($valorVenta, $cantidad), 10);
        } else {
            $valorUnitario = Decimal::normalizar((string) $item->valorUnitario);
            $valorVenta = Decimal::redondear(Decimal::multiplicar($cantidad, $valorUnitario), 2);
            $igv = Decimal::redondear(Decimal::multiplicar($valorVenta, Decimal::dividir($porcentaje, '100')), 2);
            $importeTotal = Decimal::redondear(Decimal::sumar($valorVenta, $igv), 2);
            $precioUnitario = Decimal::redondear(Decimal::dividir($importeTotal, $cantidad), 10);
        }

        return new LineaCalculada(
            orden: $orden,
            codigo: $item->codigo,
            descripcion: $item->descripcion,
            unidad: $item->unidad,
            cantidad: Decimal::compactar($cantidad),
            valorUnitario: Decimal::compactar($valorUnitario),
            precioUnitario: Decimal::compactar($precioUnitario),
            tipoAfectacionIgv: $item->afectacion,
            porcentajeIgv: Decimal::compactar($porcentaje),
            valorVenta: $valorVenta,
            baseIgv: $valorVenta,
            igv: $igv,
            totalImpuestos: $igv,
            importeTotal: $importeTotal,
        );
    }

    /**
     * @param  list<LineaCalculada>  $lineas
     */
    private function totalizar(array $lineas): Totales
    {
        $grupo = [ItemSolicitud::GRAVADO => '0', ItemSolicitud::EXONERADO => '0', ItemSolicitud::INAFECTO => '0'];
        $igv = '0';

        foreach ($lineas as $linea) {
            $grupo[$linea->tipoAfectacionIgv] = Decimal::sumar($grupo[$linea->tipoAfectacionIgv], $linea->valorVenta);
            $igv = Decimal::sumar($igv, $linea->igv);
        }

        $gravadas = Decimal::redondear($grupo[ItemSolicitud::GRAVADO], 2);
        $exoneradas = Decimal::redondear($grupo[ItemSolicitud::EXONERADO], 2);
        $inafectas = Decimal::redondear($grupo[ItemSolicitud::INAFECTO], 2);
        $igv = Decimal::redondear($igv, 2);
        $valorVenta = Decimal::redondear(Decimal::sumar(Decimal::sumar($gravadas, $exoneradas), $inafectas), 2);
        $total = Decimal::redondear(Decimal::sumar($valorVenta, $igv), 2);

        return new Totales(
            operacionesGravadas: $gravadas,
            operacionesExoneradas: $exoneradas,
            operacionesInafectas: $inafectas,
            igv: $igv,
            totalImpuestos: $igv,
            valorVenta: $valorVenta,
            subTotal: $total,
            importeTotal: $total,
        );
    }
}
