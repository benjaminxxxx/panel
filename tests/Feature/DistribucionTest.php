<?php

use App\Models\Version;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $par = sodium_crypto_sign_keypair();
    config(['licencias.llave_privada' => base64_encode(sodium_crypto_sign_secretkey($par))]);
    Storage::fake('local');
    $this->carpeta = base_path('distribucion/panaderia');
    $this->respaldo = File::isDirectory($this->carpeta) ? sys_get_temp_dir().'/dist-'.uniqid() : null;
    if ($this->respaldo) {
        File::moveDirectory($this->carpeta, $this->respaldo);
    }
    File::ensureDirectoryExists($this->carpeta);
});

afterEach(function () {
    File::deleteDirectory($this->carpeta);
    if ($this->respaldo) {
        File::moveDirectory($this->respaldo, $this->carpeta);
    }
});

test('los instaladores que llegan con git se firman y publican una sola vez', function () {
    file_put_contents("{$this->carpeta}/Panaderia-Setup-1.2.0.exe", 'instalador');
    file_put_contents("{$this->carpeta}/version.json", json_encode([
        'version' => '1.2.0', 'plataforma' => 'windows', 'archivo' => 'Panaderia-Setup-1.2.0.exe',
        'sha256' => hash('sha256', 'instalador'), 'notas' => 'Promociones 3 x S/ 1',
    ]));

    $this->artisan('versiones:sincronizar')->expectsOutputToContain('panaderia 1.2.0 (windows) publicada')->assertSuccessful();
    $v = Version::ultimaPublicada('windows', 'panaderia');
    expect($v->version)->toBe('1.2.0')->and($v->notas)->toBe('Promociones 3 x S/ 1');
    Storage::disk('local')->assertExists('versiones/Panaderia-Setup-1.2.0.exe');

    $this->artisan('versiones:sincronizar')->expectsOutput('Nada nuevo en distribucion/.');
    expect(Version::count())->toBe(1);
});

test('un instalador que no coincide con su huella no se publica', function () {
    file_put_contents("{$this->carpeta}/Panaderia-Setup-1.2.1.exe", 'incompleto');
    file_put_contents("{$this->carpeta}/version.json", json_encode([
        'version' => '1.2.1', 'archivo' => 'Panaderia-Setup-1.2.1.exe', 'sha256' => hash('sha256', 'otro'),
    ]));
    $this->artisan('versiones:sincronizar')->expectsOutputToContain('no coincide');
    expect(Version::count())->toBe(0);
});
