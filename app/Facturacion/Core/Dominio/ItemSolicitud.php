<?php

namespace App\Facturacion\Core\Dominio;

/**
 * Ítem tal como lo envía el sistema cliente. Se indica UNA de estas tres formas de precio:
 *
 * - importe:         total de la línea CON IGV (monto fijado que cobró la caja). La API deriva el
 *                    precio unitario; el importe se respeta al céntimo. Recomendado para ventas con
 *                    descuentos o precios que no dividen exacto (ej. 3 unidades por S/ 100.00).
 * - precioUnitario:  precio unitario CON IGV.
 * - valorUnitario:   valor unitario SIN IGV.
 *
 * afectacion (catálogo 07): 10 gravado (por defecto), 20 exonerado (Apéndice I: huevos, leche
 * cruda, frutas...; Amazonía), 30 inafecto. porcentajeIgv: tasa total de la línea gravada
 * (18 general; 10.5 restaurantes y hoteles MYPE en 2026). En exonerado e inafecto no hay IGV.
 */
final readonly class ItemSolicitud
{
    public const GRAVADO = '10';

    public const EXONERADO = '20';

    public const INAFECTO = '30';

    public function __construct(
        public string $descripcion,
        public string $cantidad,
        public string $unidad = 'NIU',
        public ?string $codigo = null,
        public ?string $precioUnitario = null,
        public ?string $valorUnitario = null,
        public ?string $importe = null,
        public string $afectacion = self::GRAVADO,
        public ?string $porcentajeIgv = null,
    ) {}

    public function gravado(): bool
    {
        return $this->afectacion === self::GRAVADO;
    }
}
