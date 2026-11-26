<?php

use App\Consultas\ConsultaSunat;
use App\Consultas\PadronRuc;
use App\Models\Cliente;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    $this->local = new Cliente(['aplicacion' => 'panaderia', 'nombre' => 'Panadería Rosa', 'codigo' => 'panaderia-rosa']);
    $this->local->nuevaClave('clave-pan');
    $this->local->save();
});

function padronDePrueba(): string
{
    $archivo = tempnam(sys_get_temp_dir(), 'padron').'.txt';
    file_put_contents($archivo, mb_convert_encoding(
        "RUC|NOMBRE O RAZÓN SOCIAL|ESTADO DEL CONTRIBUYENTE|CONDICIÓN DE DOMICILIO|UBIGEO|TIPO DE VÍA|NOMBRE DE VÍA|CÓDIGO DE ZONA|TIPO DE ZONA|NÚMERO|INTERIOR|LOTE|DEPARTAMENTO|MANZANA|KILÓMETRO|\n".
        "20131312955|SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT|ACTIVO|HABIDO|150101|AV.|GARCILASO DE LA VEGA|-|-|1472|-|-|-|-|-|\n".
        "10480048364|SULCA BASILIO ROBERTO CARLOS|ACTIVO|HABIDO|150132|JR.|LOS PANES|URB.|SAN JUAN|120|-|-|-|B|-|\n".
        "basura sin formato\n", 'ISO-8859-1', 'UTF-8'));

    return $archivo;
}

test('el padrón oficial se importa y responde RUC y DNI sin ir a SUNAT', function () {
    Http::fake(); // si consultara la web, la prueba lo notaría
    expect(app(PadronRuc::class)->importar(padronDePrueba()))->toBe(2);

    $r = $this->getJson('/api/v1/consultas/ruc/20131312955', ['Authorization' => 'Bearer clave-pan'])->assertOk();
    expect($r->json())->toMatchArray([
        'razon_social' => 'SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT',
        'estado' => 'ACTIVO', 'condicion' => 'HABIDO', 'direccion' => 'AV. GARCILASO DE LA VEGA NRO. 1472', 'fuente' => 'padron',
    ]);
    $this->getJson('/api/v1/consultas/dni/48004836', ['Authorization' => 'Bearer clave-pan'])
        ->assertOk()->assertJson(['nombre_completo' => 'SULCA BASILIO ROBERTO CARLOS', 'ruc' => '10480048364']);
    Http::assertNothingSent();

    // Reimportar reemplaza el padrón completo (sin duplicar).
    expect(app(PadronRuc::class)->importar(padronDePrueba()))->toBe(2);
});

test('sin padrón consulta la página de SUNAT con una sesión nueva por intento', function () {
    $html = file_get_contents(base_path('tests/fixtures/sunat-ruc.html'));
    $sesiones = 0;
    Http::fake(function (Request $r) use ($html, &$sesiones) {
        if ($r->method() === 'GET') {
            $sesiones++;

            return Http::response('', 200, ['Set-Cookie' => "JSESSIONID=s{$sesiones}; Path=/"]);
        }
        parse_str($r->body(), $form);
        if ($form['accion'] === 'consPorRazonSoc') {
            return Http::response('<input type="hidden" name="numRnd" value="123">');
        }

        // La primera sesión falla como lo hacía SUNAT; la segunda responde bien.
        return $sesiones === 1 ? Http::response('<html>error</html>') : Http::response($html);
    });

    $datos = app(ConsultaSunat::class)->ruc('20131312955');
    expect($sesiones)->toBe(2)
        ->and($datos['razon_social'])->toBe('SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT')
        ->and($datos['estado'])->toBe('ACTIVO')
        ->and($datos['direccion'])->toBe('AV. GARCILASO DE LA VEGA NRO. 1472')
        ->and($datos['departamento'])->toBe('LIMA')
        ->and($datos['agente_retencion'])->toBeTrue();

    // Cada sesión lleva solo su propia cookie (el bug del servicio anterior: las acumulaba).
    Http::assertSent(fn (Request $r) => ! str_contains($r->header('Cookie')[0] ?? '', 's1') || ! str_contains($r->header('Cookie')[0] ?? '', 's2'));
});

test('si SUNAT no responde se informa 503 para reintentar, no "no existe"', function () {
    Http::fake(fn () => Http::response('Just a moment...', 403));
    $this->getJson('/api/v1/consultas/ruc/20131312955', ['Authorization' => 'Bearer clave-pan'])->assertStatus(503);
});

test('valida el número y exige una clave', function () {
    $this->getJson('/api/v1/consultas/ruc/20131312955')->assertUnauthorized();
    $this->getJson('/api/v1/consultas/ruc/20131312956', ['Authorization' => 'Bearer clave-pan'])->assertUnprocessable();
    $this->getJson('/api/v1/consultas/dni/123', ['Authorization' => 'Bearer clave-pan'])->assertUnprocessable();
});

test('DNI con proveedor configurado (RENIEC)', function () {
    config(['consultas.dni_url' => 'https://proveedor.test/dni/{dni}', 'consultas.dni_token' => 'x']);
    Http::fake(['proveedor.test/*' => Http::response(['first_name' => 'ANA MARIA', 'first_last_name' => 'QUISPE', 'second_last_name' => 'MAMANI'])]);
    $this->getJson('/api/v1/consultas/dni/12345678', ['Authorization' => 'Bearer clave-pan'])
        ->assertOk()->assertJson(['nombre_completo' => 'QUISPE MAMANI ANA MARIA', 'nombres' => 'ANA MARIA', 'fuente' => 'reniec']);
});
