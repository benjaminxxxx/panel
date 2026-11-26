<?php

namespace App\Facturacion\Core;

use App\Facturacion\Core\Calculo\CalculadoraComprobante;
use App\Facturacion\Core\Dominio\DocumentoElectronico;
use App\Facturacion\Core\Dominio\Emisor;
use App\Facturacion\Core\Dominio\ResultadoCalculo;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Texto\MontoEnLetras;
use App\Facturacion\Core\Validacion\ComprobanteInvalido;
use App\Facturacion\Core\Validacion\ValidadorComprobante;
use DateTimeImmutable;

/**
 * Punto de entrada del core: valida, calcula y arma el documento. No sabe de base de datos ni HTTP.
 */
final class PreparadorComprobante
{
    public function __construct(
        private readonly CalculadoraComprobante $calculadora = new CalculadoraComprobante,
    ) {}

    /**
     * @throws ComprobanteInvalido
     */
    public function preparar(SolicitudComprobante $solicitud, DateTimeImmutable $ahora): ResultadoCalculo
    {
        $validador = new ValidadorComprobante;
        $validador->validarSolicitud($solicitud, $ahora);

        $calculo = $this->calculadora->calcular($solicitud);
        $validador->validarCalculo($solicitud, $calculo);

        return $calculo;
    }

    public function documento(
        Emisor $emisor,
        SolicitudComprobante $solicitud,
        int $correlativo,
        ResultadoCalculo $calculo,
    ): DocumentoElectronico {
        return new DocumentoElectronico(
            emisor: $emisor,
            tipo: $solicitud->tipo,
            serie: $solicitud->serie,
            correlativo: $correlativo,
            fechaEmision: $solicitud->fechaEmision,
            moneda: $solicitud->moneda,
            cliente: $solicitud->cliente,
            lineas: $calculo->lineas,
            totales: $calculo->totales,
            montoEnLetras: MontoEnLetras::convertir($calculo->totales->importeTotal, $solicitud->moneda),
            observacion: $solicitud->observacion,
            afectado: $solicitud->afectado,
            detraccion: $solicitud->detraccion,
        );
    }
}
