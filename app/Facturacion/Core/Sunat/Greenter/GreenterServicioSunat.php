<?php

namespace App\Facturacion\Core\Sunat\Greenter;

use App\Facturacion\Core\Dominio\DocumentoElectronico;
use App\Facturacion\Core\Dominio\LineaCalculada;
use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Sunat\ClasificadorRespuesta;
use App\Facturacion\Core\Sunat\Credenciales;
use App\Facturacion\Core\Sunat\ResultadoEnvio;
use App\Facturacion\Core\Sunat\ServicioSunat;
use App\Facturacion\Core\Sunat\XmlFirmado;
use DOMDocument;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Sale\Detraction;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\See;
use RuntimeException;
use Throwable;

final class GreenterServicioSunat implements ServicioSunat
{
    public function __construct(
        private readonly ?string $directorioCache = null,
    ) {}

    public function firmar(DocumentoElectronico $documento, Credenciales $credenciales): XmlFirmado
    {
        $xml = $this->see($credenciales)->getXmlSigned(
            $documento->tipo->esNota() ? $this->nota($documento) : $this->factura($documento)
        );

        if ($xml === null || $xml === '') {
            throw new RuntimeException("No se pudo generar el XML firmado de {$documento->nombre()}.");
        }

        return new XmlFirmado($documento->nombre(), $xml, $this->extraerHash($xml));
    }

    public function enviar(XmlFirmado $xml, Credenciales $credenciales): ResultadoEnvio
    {
        try {
            $resultado = $this->see($credenciales)->sendXml($this->claseDeDocumento($xml), $xml->nombre, $xml->contenido);
        } catch (Throwable $e) {
            return new ResultadoEnvio(EstadoComprobante::ErrorEnvio, null, 'Fallo de comunicación con SUNAT: '.$e->getMessage());
        }

        if (! $resultado instanceof BillResult) {
            return new ResultadoEnvio(EstadoComprobante::ErrorEnvio, null, 'SUNAT no devolvió respuesta.');
        }

        if (! $resultado->isSuccess()) {
            $error = $resultado->getError();
            $codigo = $error?->getCode();

            return new ResultadoEnvio(
                ClasificadorRespuesta::desdeError($codigo),
                $codigo,
                trim((string) $error?->getMessage()) ?: 'Error sin descripción.',
            );
        }

        $cdr = $resultado->getCdrResponse();
        if ($cdr === null) {
            return new ResultadoEnvio(EstadoComprobante::ErrorEnvio, null, 'SUNAT respondió sin CDR.', [], $resultado->getCdrZip());
        }

        $codigo = (string) $cdr->getCode();
        $notas = array_values(array_map('strval', $cdr->getNotes() ?? []));

        return new ResultadoEnvio(
            ClasificadorRespuesta::desdeCdr($codigo, $notas),
            $codigo,
            (string) $cdr->getDescription(),
            $notas,
            $resultado->getCdrZip(),
        );
    }

    private function see(Credenciales $credenciales): See
    {
        $see = new See;
        $see->setCertificate($credenciales->certificado->pem());
        $see->setService($credenciales->entorno->urlFacturas());
        $see->setClaveSOL($credenciales->ruc, $credenciales->usuarioSol, $credenciales->claveSol);
        $see->setCachePath($this->directorioCache);

        return $see;
    }

    /**
     * El tipo de documento va en el nombre del archivo (RUC-TIPO-SERIE-NÚMERO): así el reenvío de
     * un XML ya firmado sabe con qué servicio enviarlo sin volver a armar el documento.
     *
     * @return class-string<Invoice|Note>
     */
    private function claseDeDocumento(XmlFirmado $xml): string
    {
        return TipoComprobante::tryFrom(explode('-', $xml->nombre)[1] ?? '')?->esNota() ? Note::class : Invoice::class;
    }

    private function factura(DocumentoElectronico $doc): Invoice
    {
        $totales = $doc->totales;

        $leyendas = [(new Legend)->setCode('1000')->setValue($doc->montoEnLetras)];
        $detraccion = null;
        if ($d = $doc->detraccion) {
            $detraccion = (new Detraction)
                ->setCodBienDetraccion($d->codigo) // Catálogo 54
                ->setCodMedioPago($d->medioPago) // Catálogo 59
                ->setCtaBanco($d->cuenta)
                ->setPercent((float) $d->porcentaje)
                ->setMount((float) $d->monto($totales->importeTotal));
            $leyendas[] = (new Legend)->setCode('2006')->setValue('Operación sujeta a detracción');
        }

        return (new Invoice)
            ->setUblVersion('2.1')
            ->setTipoOperacion($detraccion ? '1001' : '0101') // Catálogo 51: 1001 = operación sujeta a detracción
            ->setDetraccion($detraccion)
            ->setTipoDoc($doc->tipo->value)
            ->setSerie($doc->serie)
            ->setCorrelativo((string) $doc->correlativo)
            ->setFechaEmision($doc->fechaEmision)
            ->setFormaPago(new FormaPagoContado)
            ->setTipoMoneda($doc->moneda)
            ->setCompany($this->empresa($doc))
            ->setClient($this->cliente($doc))
            ->setMtoOperGravadas((float) $totales->operacionesGravadas)
            ->setMtoOperExoneradas((float) $totales->operacionesExoneradas)
            ->setMtoOperInafectas((float) $totales->operacionesInafectas)
            ->setMtoIGV((float) $totales->igv)
            ->setTotalImpuestos((float) $totales->totalImpuestos)
            ->setValorVenta((float) $totales->valorVenta)
            ->setSubTotal((float) $totales->subTotal)
            ->setMtoImpVenta((float) $totales->importeTotal)
            ->setObservacion($doc->observacion)
            ->setDetails(array_map($this->detalle(...), $doc->lineas))
            ->setLegends($leyendas);
    }

    /**
     * Nota de crédito (07) o de débito (08): mismo documento UBL, cambia el tipo y el catálogo del motivo.
     */
    private function nota(DocumentoElectronico $doc): Note
    {
        $afectado = $doc->afectado ?? throw new RuntimeException("La nota {$doc->nombre()} no indica el comprobante que modifica.");
        $totales = $doc->totales;

        $nota = (new Note)
            ->setTipDocAfectado($afectado->tipo->value)
            ->setNumDocfectado($afectado->numero())
            ->setCodMotivo($afectado->motivo->value) // Catálogo 09 (crédito) o 10 (débito)
            ->setDesMotivo($afectado->descripcionMotivo)
            ->setValorVenta((float) $totales->valorVenta)
            ->setSubTotal((float) $totales->subTotal);

        $nota->setUblVersion('2.1')
            ->setTipoDoc($doc->tipo->value)
            ->setSerie($doc->serie)
            ->setCorrelativo((string) $doc->correlativo)
            ->setFechaEmision($doc->fechaEmision)
            ->setTipoMoneda($doc->moneda)
            ->setCompany($this->empresa($doc))
            ->setClient($this->cliente($doc))
            ->setMtoOperGravadas((float) $totales->operacionesGravadas)
            ->setMtoOperExoneradas((float) $totales->operacionesExoneradas)
            ->setMtoOperInafectas((float) $totales->operacionesInafectas)
            ->setMtoIGV((float) $totales->igv)
            ->setTotalImpuestos((float) $totales->totalImpuestos)
            ->setMtoImpVenta((float) $totales->importeTotal)
            ->setDetails(array_map($this->detalle(...), $doc->lineas))
            ->setLegends([(new Legend)->setCode('1000')->setValue($doc->montoEnLetras)]);

        return $nota;
    }

    private function empresa(DocumentoElectronico $doc): Company
    {
        $emisor = $doc->emisor;

        return (new Company)
            ->setRuc($emisor->ruc)
            ->setRazonSocial($emisor->razonSocial)
            ->setNombreComercial($emisor->nombreComercial)
            ->setAddress((new Address)
                ->setUbigueo($emisor->ubigeo)
                ->setDepartamento($emisor->departamento)
                ->setProvincia($emisor->provincia)
                ->setDistrito($emisor->distrito)
                ->setUrbanizacion($emisor->urbanizacion ?: '-')
                ->setDireccion($emisor->direccion)
                ->setCodLocal($emisor->codigoLocal));
    }

    private function cliente(DocumentoElectronico $doc): Client
    {
        $cliente = (new Client)
            ->setTipoDoc($doc->cliente->tipoDocumento->value)
            ->setNumDoc($doc->cliente->numeroDocumento)
            ->setRznSocial($doc->cliente->razonSocial)
            ->setEmail($doc->cliente->email);

        if ($doc->cliente->direccion) {
            $cliente->setAddress((new Address)->setDireccion($doc->cliente->direccion));
        }

        return $cliente;
    }

    private function detalle(LineaCalculada $linea): SaleDetail
    {
        return (new SaleDetail)
            ->setCodProducto($linea->codigo)
            ->setUnidad($linea->unidad)
            ->setCantidad((float) $linea->cantidad)
            ->setDescripcion($linea->descripcion)
            ->setMtoValorUnitario((float) $linea->valorUnitario)
            ->setMtoPrecioUnitario((float) $linea->precioUnitario)
            ->setMtoValorVenta((float) $linea->valorVenta)
            ->setMtoBaseIgv((float) $linea->baseIgv)
            ->setPorcentajeIgv((float) $linea->porcentajeIgv)
            ->setIgv((float) $linea->igv)
            ->setTipAfeIgv($linea->tipoAfectacionIgv)
            ->setTotalImpuestos((float) $linea->totalImpuestos);
    }

    private function extraerHash(string $xml): string
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $nodo = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'DigestValue')->item(0);

        return $nodo->nodeValue ?? '';
    }
}
