<?php

namespace Tests\Support;

use App\Facturacion\Core\Certificado\GeneradorCertificadoPrueba;
use App\Facturacion\Core\Dominio\Cliente;
use App\Facturacion\Core\Dominio\Emisor;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;
use DateTimeImmutable;
use DateTimeZone;

final class Facturacion
{
    /** RUC válido (dígito verificador correcto) para usar como cliente en pruebas. */
    public const RUC_CLIENTE = '20100070970';

    /** RUC que SUNAT beta acepta como emisor con MODDATOS. */
    public const RUC_EMISOR_BETA = '20000000001';

    public static function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
    }

    public static function emisor(string $ruc = self::RUC_EMISOR_BETA): Emisor
    {
        return new Emisor(
            ruc: $ruc,
            razonSocial: 'EMPRESA DE PRUEBA S.A.C.',
            nombreComercial: 'PRUEBA',
            direccion: 'AV. LOS OLIVOS 123',
            ubigeo: '150101',
            departamento: 'LIMA',
            provincia: 'LIMA',
            distrito: 'LIMA',
        );
    }

    public static function clienteRuc(): Cliente
    {
        return new Cliente(TipoDocumentoIdentidad::Ruc, self::RUC_CLIENTE, 'SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA');
    }

    /**
     * @param  list<ItemSolicitud>|null  $items
     */
    public static function solicitud(
        TipoComprobante $tipo = TipoComprobante::Factura,
        ?Cliente $cliente = null,
        ?array $items = null,
        ?string $serie = null,
        ?DateTimeImmutable $fecha = null,
    ): SolicitudComprobante {
        return new SolicitudComprobante(
            tipo: $tipo,
            serie: $serie ?? ($tipo === TipoComprobante::Factura ? 'F001' : 'B001'),
            fechaEmision: $fecha ?? self::ahora(),
            cliente: $cliente ?? ($tipo === TipoComprobante::Factura ? self::clienteRuc() : Cliente::varios()),
            items: $items ?? [new ItemSolicitud('PRODUCTO 1', '2', 'NIU', 'P001', precioUnitario: '59.00')],
        );
    }

    /**
     * Certificado autofirmado (SUNAT beta no valida la cadena de confianza).
     *
     * @return array{pem: string, pfx: string, clave: string}
     */
    public static function certificadoAutofirmado(string $cn = self::RUC_EMISOR_BETA, int $dias = 365): array
    {
        return GeneradorCertificadoPrueba::generar($cn, $dias);
    }
}
