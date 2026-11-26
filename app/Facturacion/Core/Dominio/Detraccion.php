<?php

namespace App\Facturacion\Core\Dominio;

use App\Facturacion\Core\Calculo\Decimal;

/**
 * Operación sujeta al SPOT (detracción). Solo facturas mayores a S/ 700. El cliente deposita
 * el porcentaje en la cuenta del Banco de la Nación del emisor; la factura lo informa.
 * codigo: catálogo 54 (ej. 025 fabricación de bienes por encargo, 037 demás servicios gravados).
 */
final readonly class Detraccion
{
    public const LIMITE = '700.00';

    public function __construct(
        public string $codigo,
        public string $porcentaje,
        public string $cuenta,
        public string $medioPago = '001', // catálogo 59: depósito en cuenta
    ) {}

    public function monto(string $total): string
    {
        return Decimal::redondear(Decimal::multiplicar($total, Decimal::dividir($this->porcentaje, '100')), 2);
    }

    /** @return array{codigo: string, porcentaje: string, cuenta: string, medio_pago: string, monto: string} */
    public function aArreglo(string $total): array
    {
        return ['codigo' => $this->codigo, 'porcentaje' => $this->porcentaje, 'cuenta' => $this->cuenta,
            'medio_pago' => $this->medioPago, 'monto' => $this->monto($total)];
    }
}
