<?php

use App\Licencias\Firmador;
use App\Licencias\PublicadorVersiones;
use App\Models\CatalogoProducto;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Version;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $par = sodium_crypto_sign_keypair();
    $this->publica = sodium_crypto_sign_publickey($par);
    config(['licencias.llave_privada' => base64_encode(sodium_crypto_sign_secretkey($par))]);
    Carbon::setTestNow('2026-11-26 10:00:00');
    Storage::fake('local');

    $this->panaderia = new Cliente(['aplicacion' => 'panaderia', 'nombre' => 'Panadería Rosa', 'codigo' => 'panaderia-rosa']);
    $this->panaderia->nuevaClave('clave-pan');
    $this->panaderia->save();
    $this->restaurante = new Cliente(['nombre' => 'Pollería Ana', 'codigo' => 'polleria-ana']);
    $this->restaurante->nuevaClave('clave-res');
    $this->restaurante->save();
    $this->admin = User::factory()->admin()->create();
});

function subirVersionDe(string $app, string $version): void
{
    $archivo = tempnam(sys_get_temp_dir(), 'inst');
    file_put_contents($archivo, "instalador {$app} {$version}");
    app(PublicadorVersiones::class)->publicar($version, $archivo, "Notas {$version}", true, 'windows', $app);
}

test('cada local ve solo las actualizaciones de su sistema, firmadas con su nombre', function () {
    subirVersionDe('restaurante', '2.0.0');
    subirVersionDe('panaderia', '1.0.1');

    $r = $this->getJson('/api/v1/actualizacion?version=1.0.0', ['Authorization' => 'Bearer clave-pan'])->assertOk();
    expect($r->json('version'))->toBe('1.0.1');
    $firma = base64_decode($r->json('firma'));
    expect(sodium_crypto_sign_verify_detached($firma, 'panaderia|actualizacion|1.0.1|'.$r->json('sha256'), $this->publica))->toBeTrue()
        ->and(sodium_crypto_sign_verify_detached($firma, Firmador::mensajeVersion('1.0.1', $r->json('sha256')), $this->publica))->toBeFalse();

    $this->get('/api/v1/actualizacion/1.0.1/descargar', ['Authorization' => 'Bearer clave-pan'])
        ->assertOk()->assertDownload('Panaderia-Setup-1.0.1.exe');
    $this->get('/api/v1/actualizacion/2.0.0/descargar', ['Authorization' => 'Bearer clave-pan'])->assertNotFound();

    $this->getJson('/api/v1/actualizacion?version=1.0.0', ['Authorization' => 'Bearer clave-res'])->assertJson(['version' => '2.0.0']);
});

test('la licencia dice de qué sistema es', function () {
    $r = $this->getJson('/api/v1/licencia', ['Authorization' => 'Bearer clave-pan'])->assertOk();
    expect(json_decode(base64_decode($r->json('datos')), true)['aplicacion'])->toBe('panaderia');
});

test('un código de la panadería no activa un restaurante ni al revés', function () {
    $codigo = $this->panaderia->nuevoCodigoActivacion();
    $this->postJson('/api/v1/activar', ['codigo' => $codigo])->assertUnprocessable()->assertJsonPath('error', fn ($e) => str_contains($e, 'Panadería'));
    $this->postJson('/api/v1/activar', ['codigo' => $codigo, 'aplicacion' => 'panaderia'])->assertOk();

    $codigo = $this->restaurante->nuevoCodigoActivacion();
    $this->postJson('/api/v1/activar', ['codigo' => $codigo, 'aplicacion' => 'panaderia'])->assertUnprocessable();
    $this->postJson('/api/v1/activar', ['codigo' => $codigo])->assertOk(); // el restaurante anterior no envía el sistema
});

test('el catálogo público busca por nombre, marca o código y pagina', function () {
    CatalogoProducto::create(['codigo_barras' => '7751271001212', 'nombre' => 'Leche evaporada entera', 'marca' => 'Gloria', 'presentacion' => '400 g', 'categoria' => 'Lácteos', 'precio_sugerido' => '4.50']);
    CatalogoProducto::create(['codigo_barras' => '7750885000017', 'nombre' => 'Café instantáneo', 'marca' => 'Kirma', 'presentacion' => '50 g']);
    CatalogoProducto::create(['codigo_barras' => '7750000000000', 'nombre' => 'Producto retirado', 'activo' => false]);

    $r = $this->getJson('/api/v1/catalogo?q=gloria')->assertOk();
    expect($r->json('total'))->toBe(1)
        ->and($r->json('data.0'))->toMatchArray(['codigo_barras' => '7751271001212', 'precio_sugerido' => '4.50', 'unidad' => 'NIU', 'foto' => '']);
    expect($this->getJson('/api/v1/catalogo?q=775127')->json('total'))->toBe(1);
    expect($this->getJson('/api/v1/catalogo?q=leche 400')->json('total'))->toBe(1);
    expect($this->getJson('/api/v1/catalogo')->json('total'))->toBe(2); // los ocultos no salen
});

test('el proveedor importa el catálogo desde un CSV de Excel', function () {
    $csv = "\xEF\xBB\xBFcodigo_barras;nombre;marca;presentacion;categoria;unidad;precio_sugerido\n".
        "7751271001212;Leche evaporada;Gloria;400 g;Lácteos;NIU;4,50\n".
        "123;Sin código válido;;;;;\n".
        "7750885000017;Café instantáneo;Kirma;50 g;Abarrotes;;8.90\n";
    $this->actingAs($this->admin)
        ->post(route('catalogo.importar'), ['archivo' => UploadedFile::fake()->createWithContent('productos.csv', $csv)])
        ->assertRedirect(route('catalogo.index'))->assertSessionHas('ok', 'Importación lista: 2 nuevos, 0 actualizados.')
        ->assertSessionHasErrors('archivo');
    expect(CatalogoProducto::where('codigo_barras', '7751271001212')->first())
        ->precio_sugerido->toBe('4.50')->categoria->toBe('Lácteos');

    $this->actingAs($this->admin)->get(route('catalogo.index', ['q' => 'gloria']))->assertOk()->assertSee('Leche evaporada');
});

test('el panel crea clientes de panadería y su página de instalación', function () {
    $this->actingAs($this->admin)->post(route('clientes.store'), ['aplicacion' => 'panaderia', 'nombre' => 'Panadería Sol'])->assertRedirect();
    expect(Cliente::where('nombre', 'Panadería Sol')->value('aplicacion'))->toBe('panaderia');
    subirVersionDe('panaderia', '1.0.0');
    $this->actingAs($this->admin)->get(route('descargas'))->assertOk()->assertSee('Panaderia-Setup-1.0.0.exe');
    $this->actingAs($this->admin)->get(route('descargas.windows', 'panaderia'))->assertDownload('Panaderia-Setup-1.0.0.exe');
});

test('el proveedor permite empezar de cero una vez más y el local lo consume', function () {
    $this->actingAs($this->admin)->post(route('clientes.reinicio', $this->panaderia))->assertRedirect();
    $permiso = $this->panaderia->fresh()->reinicio_permiso;
    expect($permiso)->toHaveLength(16);

    $datos = fn ($r) => json_decode(base64_decode($r->json('datos')), true);
    expect($datos($this->getJson('/api/v1/licencia', ['Authorization' => 'Bearer clave-pan']))['reinicio'])->toBe($permiso);

    $this->getJson('/api/v1/licencia', ['Authorization' => 'Bearer clave-pan', 'X-Reinicio-Usado' => 'otro'])->assertOk();
    expect($this->panaderia->fresh()->reinicio_permiso)->toBe($permiso);
    $r = $this->getJson('/api/v1/licencia', ['Authorization' => 'Bearer clave-pan', 'X-Reinicio-Usado' => $permiso]);
    expect($datos($r)['reinicio'])->toBe('')->and($this->panaderia->fresh()->reinicio_permiso)->toBeNull();
});

test('el script de compilación publica una versión de panadería con el token', function () {
    config(['licencias.token_publicacion' => 'token-ci']);
    $archivo = UploadedFile::fake()->createWithContent('Panaderia-Setup-1.0.1.exe', 'instalador nuevo');
    $this->post('/api/v1/versiones', ['aplicacion' => 'panaderia', 'version' => '1.0.1', 'notas' => 'Paleta de pan', 'publicar' => '1', 'archivo' => $archivo],
        ['Authorization' => 'Bearer token-ci', 'Accept' => 'application/json'])
        ->assertCreated()->assertJson(['aplicacion' => 'panaderia', 'version' => '1.0.1', 'publicada' => true]);
    expect(Version::ultimaPublicada('windows', 'panaderia')->version)->toBe('1.0.1');
    Storage::disk('local')->assertExists('versiones/Panaderia-Setup-1.0.1.exe');

    $this->actingAs($this->admin)->get(route('versiones.index'))->assertOk()->assertSee('Publicar sin entrar aquí');
    $this->actingAs($this->admin)->get(route('descargas'))->assertOk()->assertSee('Panaderia-Setup-1.0.1.exe');
});
