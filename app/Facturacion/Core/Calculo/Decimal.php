<?php

namespace App\Facturacion\Core\Calculo;

use InvalidArgumentException;

/**
 * Aritmética decimal exacta sobre strings (bcmath).
 *
 * Nunca usar float para montos: 0.1 + 0.2 != 0.3 y SUNAT valida al céntimo.
 */
final class Decimal
{
    /** Escala interna para operaciones intermedias. */
    private const ESCALA = 14;

    /**
     * Convierte una entrada externa a decimal plano (sin exponentes ni espacios).
     *
     * @return numeric-string
     */
    public static function normalizar(string|int|float $valor): string
    {
        if (is_float($valor)) {
            // Evita notación científica y ruido binario (ej: 0.30000000000000004).
            $valor = rtrim(rtrim(number_format($valor, 10, '.', ''), '0'), '.');
        }

        $valor = trim((string) $valor);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $valor) || ! is_numeric($valor)) {
            throw new InvalidArgumentException("Valor numérico inválido: '{$valor}'.");
        }

        return $valor;
    }

    /**
     * @return numeric-string
     */
    public static function sumar(string $a, string $b): string
    {
        return bcadd(self::numero($a), self::numero($b), self::ESCALA);
    }

    /**
     * @return numeric-string
     */
    public static function restar(string $a, string $b): string
    {
        return bcsub(self::numero($a), self::numero($b), self::ESCALA);
    }

    /**
     * @return numeric-string
     */
    public static function multiplicar(string $a, string $b): string
    {
        return bcmul(self::numero($a), self::numero($b), self::ESCALA);
    }

    /**
     * @return numeric-string
     */
    public static function dividir(string $a, string $b): string
    {
        if (self::comparar($b, '0') === 0) {
            throw new InvalidArgumentException('División entre cero.');
        }

        return bcdiv(self::numero($a), self::numero($b), self::ESCALA);
    }

    /**
     * Redondeo "half up" (lejos de cero), el que usa SUNAT.
     *
     * @return numeric-string
     */
    public static function redondear(string $valor, int $decimales = 2): string
    {
        $valor = self::numero($valor);
        $mitad = self::numero('0.'.str_repeat('0', $decimales).'5');

        $ajustado = str_starts_with($valor, '-')
            ? bcsub($valor, $mitad, $decimales + 1)
            : bcadd($valor, $mitad, $decimales + 1);

        $resultado = bcadd($ajustado, '0', $decimales);

        // Evita "-0.00".
        return bccomp($resultado, '0', $decimales) === 0 ? bcadd('0', '0', $decimales) : $resultado;
    }

    public static function comparar(string $a, string $b): int
    {
        return bccomp(self::numero($a), self::numero($b), self::ESCALA);
    }

    public static function esPositivo(string $valor): bool
    {
        return self::comparar($valor, '0') > 0;
    }

    public static function cantidadDecimales(string $valor): int
    {
        $punto = strpos($valor, '.');

        return $punto === false ? 0 : strlen(rtrim(substr($valor, $punto + 1), '0'));
    }

    /**
     * Quita ceros sobrantes a la derecha: "2.500000" → "2.5", "3.00" → "3".
     */
    public static function compactar(string $valor): string
    {
        if (! str_contains($valor, '.')) {
            return $valor;
        }

        return rtrim(rtrim($valor, '0'), '.');
    }

    /**
     * @return numeric-string
     */
    private static function numero(string $valor): string
    {
        if (! is_numeric($valor)) {
            throw new InvalidArgumentException("Valor numérico inválido: '{$valor}'.");
        }

        return $valor;
    }
}
