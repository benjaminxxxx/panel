<?php

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $par = sodium_crypto_sign_keypair();
    config([
        'licencias.llave_privada' => base64_encode(sodium_crypto_sign_secretkey($par)),
        'licencias.token_publicacion' => 'token-ci',
    ]);
    Carbon::setTestNow('2026-11-25 10:00:00');
    $this->cliente = new Cliente(['nombre' => 'Pollería Ana', 'codigo' => 'polleria-ana']);
    $this->cliente->nuevaClave('clave-vieja');
    $this->cliente->save();
    $this->admin = User::factory()->create();
});

test('el código de activación entrega una clave nueva una sola vez', function () {
    $this->actingAs($this->admin)->post(route('clientes.activacion', $this->cliente))->assertRedirect();
    $codigo = session('activacion');
    expect($codigo)->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/');

    $r = $this->postJson('/api/v1/activar', ['codigo' => strtolower(str_replace('-', '', $codigo))])->assertOk();
    expect($r->json('cliente'))->toBe('polleria-ana')->and($r->json('nombre'))->toBe('Pollería Ana');
    $clave = $r->json('clave');
    $this->getJson('/api/v1/licencia', ['Authorization' => "Bearer $clave"])->assertOk();
    $this->getJson('/api/v1/licencia', ['Authorization' => 'Bearer clave-vieja'])->assertUnauthorized(); // una instalación activa

    $this->postJson('/api/v1/activar', ['codigo' => $codigo])->assertUnprocessable(); // ya se usó
});

test('el código de activación vence a las 2 horas', function () {
    $this->actingAs($this->admin)->post(route('clientes.activacion', $this->cliente));
    $codigo = session('activacion');
    Carbon::setTestNow('2026-11-25 12:01:00');
    $this->postJson('/api/v1/activar', ['codigo' => $codigo])->assertUnprocessable();
});

test('publicar una versión desde Git exige el token', function () {
    Storage::fake('local');
    $datos = ['version' => '1.2.0', 'notas' => 'Desde CI', 'publicar' => '1',
        'archivo' => UploadedFile::fake()->createWithContent('Restaurante-Setup-1.2.0.exe', 'instalador 1.2.0')];

    $this->post('/api/v1/versiones', $datos, ['Authorization' => 'Bearer otro', 'Accept' => 'application/json'])->assertUnauthorized();
    $this->post('/api/v1/versiones', $datos, ['Authorization' => 'Bearer token-ci', 'Accept' => 'application/json'])->assertCreated();

    // El proveedor descarga desde el panel el instalador más reciente.
    $res = $this->actingAs($this->admin)->get(route('descargas.windows'))->assertOk();
    expect($res->streamedContent())->toBe('instalador 1.2.0');
    $this->actingAs($this->admin)->get(route('descargas'))->assertOk()->assertSee('1.2.0');
});

test('sin token configurado no se puede publicar por API', function () {
    config(['licencias.token_publicacion' => null]);
    $this->post('/api/v1/versiones', [], ['Authorization' => 'Bearer ', 'Accept' => 'application/json'])->assertUnauthorized();
});

test('descargar exige iniciar sesión', function () {
    auth()->logout();
    $this->get(route('descargas.windows'))->assertRedirect(route('login'));
});
