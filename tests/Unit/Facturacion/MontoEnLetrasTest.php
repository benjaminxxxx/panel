<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Texto\MontoEnLetras;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MontoEnLetrasTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function montos(): array
    {
        return [
            ['0', 'SON CERO CON 00/100 SOLES'],
            ['1', 'SON UNO CON 00/100 SOLES'],
            ['0.999', 'SON UNO CON 00/100 SOLES'],
            ['16', 'SON DIECISÉIS CON 00/100 SOLES'],
            ['21', 'SON VEINTIUNO CON 00/100 SOLES'],
            ['45.10', 'SON CUARENTA Y CINCO CON 10/100 SOLES'],
            ['100', 'SON CIEN CON 00/100 SOLES'],
            ['101', 'SON CIENTO UNO CON 00/100 SOLES'],
            ['118', 'SON CIENTO DIECIOCHO CON 00/100 SOLES'],
            ['700.50', 'SON SETECIENTOS CON 50/100 SOLES'],
            ['1000', 'SON MIL CON 00/100 SOLES'],
            ['1001', 'SON MIL UNO CON 00/100 SOLES'],
            ['21000', 'SON VEINTIÚN MIL CON 00/100 SOLES'],
            ['21021', 'SON VEINTIÚN MIL VEINTIUNO CON 00/100 SOLES'],
            ['31000', 'SON TREINTA Y UN MIL CON 00/100 SOLES'],
            ['100000', 'SON CIEN MIL CON 00/100 SOLES'],
            ['101000', 'SON CIENTO UN MIL CON 00/100 SOLES'],
            ['1000000', 'SON UN MILLÓN CON 00/100 SOLES'],
            ['1234567.89', 'SON UN MILLÓN DOSCIENTOS TREINTA Y CUATRO MIL QUINIENTOS SESENTA Y SIETE CON 89/100 SOLES'],
            ['2500000.75', 'SON DOS MILLONES QUINIENTOS MIL CON 75/100 SOLES'],
            ['21000000', 'SON VEINTIÚN MILLONES CON 00/100 SOLES'],
            ['999999999.99', 'SON NOVECIENTOS NOVENTA Y NUEVE MILLONES NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE CON 99/100 SOLES'],
        ];
    }

    #[DataProvider('montos')]
    public function test_convierte(string $monto, string $esperado): void
    {
        $this->assertSame($esperado, MontoEnLetras::convertir($monto));
    }

    public function test_dolares(): void
    {
        $this->assertSame('SON DIEZ CON 00/100 DÓLARES AMERICANOS', MontoEnLetras::convertir('10', 'USD'));
    }
}
