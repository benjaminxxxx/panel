<?php

namespace Tests\Unit\Facturacion;

use App\Facturacion\Core\Validacion\Ruc;
use PHPUnit\Framework\TestCase;

class RucTest extends TestCase
{
    public function test_acepta_rucs_validos(): void
    {
        $this->assertTrue(Ruc::esValido('20100070970'));
        $this->assertTrue(Ruc::esValido('20000000001'));
        $this->assertTrue(Ruc::esValido('10467793549'));
    }

    public function test_rechaza_rucs_invalidos(): void
    {
        $this->assertFalse(Ruc::esValido('20100070971'), 'dígito verificador');
        $this->assertFalse(Ruc::esValido('30100070970'), 'prefijo');
        $this->assertFalse(Ruc::esValido('2010007097'), 'longitud');
        $this->assertFalse(Ruc::esValido('2010007097A'), 'no numérico');
        $this->assertFalse(Ruc::esValido(''), 'vacío');
    }
}
