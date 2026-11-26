<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Calculo\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_redondea_half_up(): void
    {
        $this->assertSame('2.68', Decimal::redondear('2.675', 2));
        $this->assertSame('2.67', Decimal::redondear('2.6749999', 2));
        $this->assertSame('-2.68', Decimal::redondear('-2.675', 2));
        $this->assertSame('0.00', Decimal::redondear('-0.001', 2));
        $this->assertSame('8.48', Decimal::redondear('8.475', 2));
    }

    public function test_normaliza_floats_sin_ruido_binario(): void
    {
        $this->assertSame('0.3', Decimal::normalizar(0.1 + 0.2));
        $this->assertSame('59', Decimal::normalizar(59.0));
        $this->assertSame('1.5', Decimal::normalizar(' 1.5 '));
    }

    public function test_rechaza_texto_no_numerico(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::normalizar('1e5');
    }

    public function test_cuenta_decimales_significativos(): void
    {
        $this->assertSame(2, Decimal::cantidadDecimales('1.2500'));
        $this->assertSame(0, Decimal::cantidadDecimales('3.000'));
        $this->assertSame(0, Decimal::cantidadDecimales('7'));
    }
}
