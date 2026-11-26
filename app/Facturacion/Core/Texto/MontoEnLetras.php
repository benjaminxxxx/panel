<?php

namespace App\Facturacion\Core\Texto;

use App\Facturacion\Core\Calculo\Decimal;
use InvalidArgumentException;

/**
 * Leyenda 1000 (Catálogo 52): "SON CIENTO DIECIOCHO CON 00/100 SOLES".
 */
final class MontoEnLetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS',
        6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS',
    ];

    private const MONEDAS = [
        'PEN' => 'SOLES',
        'USD' => 'DÓLARES AMERICANOS',
    ];

    public static function convertir(string $monto, string $moneda = 'PEN'): string
    {
        $monto = Decimal::redondear(Decimal::normalizar($monto), 2);

        if (str_starts_with($monto, '-')) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        [$entero, $centimos] = explode('.', $monto);

        if (strlen($entero) > 12) {
            throw new InvalidArgumentException('Monto demasiado grande para convertir a letras.');
        }

        $nombreMoneda = self::MONEDAS[$moneda] ?? throw new InvalidArgumentException("Moneda no soportada: {$moneda}");

        return 'SON '.self::enteroALetras((int) $entero)." CON {$centimos}/100 {$nombreMoneda}";
    }

    public static function enteroALetras(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }

        $millones = intdiv($numero, 1_000_000);
        $miles = intdiv($numero % 1_000_000, 1000);
        $resto = $numero % 1000;

        $partes = [];

        if ($millones > 0) {
            $partes[] = $millones === 1
                ? 'UN MILLÓN'
                : self::hastaMil($millones, true).' MILLONES';
        }

        if ($miles > 0) {
            $partes[] = $miles === 1
                ? 'MIL'
                : self::hastaMil($miles, true).' MIL';
        }

        if ($resto > 0) {
            $partes[] = self::hastaMil($resto, false);
        }

        return implode(' ', $partes);
    }

    /**
     * Convierte 1..999 (o más, para los millones de millones). Con $apocope, "UNO" final pasa a "UN"
     * (VEINTIÚN MIL, CIENTO UN MILLONES).
     */
    private static function hastaMil(int $numero, bool $apocope): string
    {
        if ($numero >= 1000) {
            return self::enteroALetras($numero);
        }

        $centena = intdiv($numero, 100);
        $resto = $numero % 100;
        $partes = [];

        if ($centena > 0) {
            $partes[] = ($numero === 100) ? 'CIEN' : self::CENTENAS[$centena];
        }

        if ($resto > 0) {
            $partes[] = self::hastaCien($resto);
        }

        $texto = implode(' ', $partes);

        if ($apocope) {
            $texto = preg_replace(['/VEINTIUNO$/u', '/UNO$/u'], ['VEINTIÚN', 'UN'], $texto) ?? $texto;
        }

        return $texto;
    }

    private static function hastaCien(int $numero): string
    {
        if ($numero < 30) {
            return self::UNIDADES[$numero];
        }

        $decena = intdiv($numero, 10);
        $unidad = $numero % 10;

        return self::DECENAS[$decena].($unidad > 0 ? ' Y '.self::UNIDADES[$unidad] : '');
    }
}
