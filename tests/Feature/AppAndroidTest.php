<?php

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $par = sodium_crypto_sign_keypair();
    config(['licencias.llave_privada' => base64_encode(sodium_crypto_sign_secretkey($par))]);
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
});

function subir($test, string $version, string $plataforma, string $contenido): void
{
    $test->actingAs($test->admin)->post(route('versiones.store'), [
        'version' => $version, 'plataforma' => $plataforma, 'publicar' => '1', 'notas' => "v$version",
        'archivo' => UploadedFile::fake()->createWithContent("app.$plataforma", $contenido),
    ])->assertSessionHasNoErrors()->assertRedirect();
}

test('Windows y Android llevan su propia numeración de versiones', function () {
    subir($this, '1.0.3', 'windows', 'instalador windows');
    subir($this, '1.0.0', 'android', 'apk 1.0.0');
    subir($this, '1.0.3', 'android', 'apk 1.0.3'); // misma versión que Windows: no choca

    $this->getJson('/api/v1/android')->assertOk()->assertJson([
        'version' => '1.0.3', 'version_code' => 10003,
    ])->assertJsonPath('url', url('/app/android/descargar'));

    expect($this->get('/app/android/descargar')->assertOk()->streamedContent())->toBe('apk 1.0.3');

    // Los locales Windows siguen viendo solo instaladores de Windows.
    $par = sodium_crypto_sign_keypair();
    $c = new Cliente(['nombre' => 'X', 'codigo' => 'x']);
    $clave = $c->nuevaClave();
    $c->save();
    $this->getJson('/api/v1/actualizacion?version=1.0.0', ['Authorization' => "Bearer $clave"])
        ->assertJson(['disponible' => true, 'version' => '1.0.3']);
    expect($this->get(route('descargas.windows'))->streamedContent())->toBe('instalador windows');
});

test('la página del QR abre la app o lleva a instalarla', function () {
    subir($this, '1.0.0', 'android', 'apk');
    auth()->logout();
    $servidor = 'http://192.168.100.4:8080';

    $android = $this->get('/app?servidor='.urlencode($servidor), ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14)'])->assertOk();
    $android->assertSee('intent://abrir?', false)
        ->assertSee('package=pe.restaurante.app', false)
        ->assertSee(urlencode($servidor), false)
        ->assertSee('Descargar la app', false);

    $iphone = $this->get('/app?servidor='.urlencode($servidor), ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)'])->assertOk();
    $iphone->assertSee($servidor, false)->assertSee('Agregar a inicio', false)->assertDontSee('intent://', false);
});

test('el QR solo acepta direcciones http del local', function () {
    $this->get('/app?servidor='.urlencode('javascript:alert(1)'))->assertOk()->assertDontSee('javascript:', false);
});

test('sin APK publicado la consulta lo dice', function () {
    $this->getJson('/api/v1/android')->assertNotFound();
});
