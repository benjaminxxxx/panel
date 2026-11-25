<?php

use App\Licencias\EstadoCliente;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $par = sodium_crypto_sign_keypair();
    $this->publica = sodium_crypto_sign_publickey($par);
    config(['licencias.llave_privada' => base64_encode(sodium_crypto_sign_secretkey($par))]);
    Carbon::setTestNow('2026-11-24 12:00:00');

    $this->cliente = new Cliente(['nombre' => 'Restaurante demo', 'codigo' => 'demo', 'vence_el' => '2026-12-24']);
    $this->clave = $this->cliente->nuevaClave('clave-de-prueba');
    $this->cliente->save();
});

function licencia($test, array $cabeceras = []): array
{
    $res = $test->getJson('/api/v1/licencia', ['Authorization' => 'Bearer '.$test->clave] + $cabeceras)->assertOk();
    $datos = base64_decode($res->json('datos'));
    expect(sodium_crypto_sign_verify_detached(base64_decode($res->json('firma')), $datos, $test->publica))->toBeTrue();

    return json_decode($datos, true);
}

test('sin clave o con clave falsa no hay licencia', function () {
    $this->getJson('/api/v1/licencia')->assertUnauthorized();
    $this->getJson('/api/v1/licencia', ['Authorization' => 'Bearer otra'])->assertUnauthorized();
});

test('la licencia va firmada y registra el contacto del local', function () {
    $l = licencia($this, ['X-Version' => '1.0.0']);
    expect($l['estado'])->toBe('activo')
        ->and($l['cliente'])->toBe('demo')
        ->and($l['vence_el'])->toBe('2026-12-24')
        ->and($l['valida_hasta'])->toBe('2026-12-01T12:00:00Z');
    expect($this->cliente->fresh()->version_reportada)->toBe('1.0.0');
});

test('bloquear a mano se refleja en la licencia con su motivo', function () {
    $this->cliente->update(['estado' => 'bloqueado', 'motivo_bloqueo' => null]);
    $l = licencia($this);
    expect($l['estado'])->toBe('bloqueado')
        ->and($l['motivo'])->toBe(config('licencias.motivo_por_defecto'));
});

test('bloqueo automático solo después de los días de gracia', function () {
    $c = $this->cliente;
    $c->vence_el = '2026-11-22'; // venció hace 2 días: aún en gracia (3)
    $e = EstadoCliente::evaluar($c, Carbon::now());
    expect($e['estado'])->toBe('activo')->and($e['aviso'])->toContain('venció');

    $c->vence_el = '2026-11-20'; // hace 4 días
    expect(EstadoCliente::evaluar($c, Carbon::now())['estado'])->toBe('bloqueado');

    $c->bloqueo_automatico = false; // la dueña decide no bloquear
    expect(EstadoCliente::evaluar($c, Carbon::now())['estado'])->toBe('activo');
});

test('aviso cuando el pago está por vencer', function () {
    $this->cliente->vence_el = '2026-11-26';
    expect(EstadoCliente::evaluar($this->cliente, Carbon::now())['aviso'])->toContain('26/11/2026');
});

test('actualización: ofrece la versión publicada más nueva, firmada, y permite descargarla', function () {
    Storage::fake('local');
    $admin = User::factory()->create();
    foreach (['1.0.9' => true, '1.0.10' => true, '1.1.0' => false] as $v => $publicar) {
        $this->actingAs($admin)->post(route('versiones.store'), [
            'version' => $v, 'notas' => "Cambios de $v", 'publicar' => $publicar ? '1' : null,
            'archivo' => UploadedFile::fake()->createWithContent('restaurante.exe', "binario $v"),
        ])->assertRedirect();
    }
    $auth = ['Authorization' => 'Bearer '.$this->clave];

    $r = $this->getJson('/api/v1/actualizacion?version=1.0.9', $auth)->assertOk();
    expect($r->json('disponible'))->toBeTrue()->and($r->json('version'))->toBe('1.0.10'); // 1.1.0 no está publicada
    $mensaje = 'restaurante|actualizacion|1.0.10|'.hash('sha256', 'binario 1.0.10');
    expect($r->json('sha256'))->toBe(hash('sha256', 'binario 1.0.10'))
        ->and(sodium_crypto_sign_verify_detached(base64_decode($r->json('firma')), $mensaje, $this->publica))->toBeTrue();

    $this->getJson('/api/v1/actualizacion?version=1.0.10', $auth)->assertOk()->assertJson(['disponible' => false]);
    expect($this->get('/api/v1/actualizacion/1.0.10/descargar', $auth)->assertOk()->streamedContent())->toBe('binario 1.0.10');
    $this->get('/api/v1/actualizacion/1.1.0/descargar', $auth)->assertNotFound(); // no publicada
    $this->get('/api/v1/actualizacion/1.0.10/descargar')->assertUnauthorized();
});

test('panel: bloquear, habilitar y registrar un pago', function () {
    $admin = User::factory()->create();
    $c = $this->cliente;

    $this->actingAs($admin)->post(route('clientes.bloquear', $c), ['motivo' => 'Debe noviembre'])->assertRedirect();
    expect($c->fresh()->estado)->toBe('bloqueado')->and(licencia($this)['motivo'])->toBe('Debe noviembre');

    $this->actingAs($admin)->post(route('clientes.habilitar', $c))->assertRedirect();
    expect(licencia($this)['estado'])->toBe('activo');

    $this->actingAs($admin)->post(route('clientes.pagos.store', $c), ['monto' => '150', 'meses' => 1])->assertRedirect();
    expect($c->fresh()->vence_el->toDateString())->toBe('2027-01-24');
});

test('el panel exige iniciar sesión', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->post(route('clientes.bloquear', $this->cliente))->assertRedirect(route('login'));
});
