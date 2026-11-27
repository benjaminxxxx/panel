<?php

use App\Models\CatalogoProducto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::fake([
        'world.openfoodfacts.org/api/v2/product/7751271024679.json*' => Http::response(['status' => 1, 'product' => [
            'product_name' => 'Leche Condensada Gloria', 'brands' => 'Gloria,Leche Gloria', 'quantity' => '393g',
            'categories_tags' => ['en:dairies', 'en:sweet-spreads', 'en:condensed-milks'],
            'image_front_small_url' => 'https://images.openfoodfacts.org/images/products/775/127/102/4679/front_es.4.200.jpg',
        ]]),
        'world.openfoodfacts.org/api/v2/product/7750000000001.json*' => Http::response(['status' => 1, 'product' => [
            'product_name' => 'Detergente en polvo', 'brands' => 'Marca X', 'quantity' => '1 kg', 'categories_tags' => ['en:detergents'],
        ]]),
        'world.openfoodfacts.org/api/v2/product/*' => Http::response(['status' => 0, 'status_verbose' => 'product not found']),
        'images.openfoodfacts.org/*' => Http::response('JPEG', 200, ['Content-Type' => 'image/jpeg']),
        'search.openfoodfacts.org/*' => Http::response(['hits' => [['code' => '7751271024679'], ['code' => '7750000000001']], 'page_count' => 1]),
    ]);
});

test('al escanear un producto de panadería que no está en el catálogo, se trae de Open Food Facts con su foto', function () {
    $r = $this->getJson('/api/v1/catalogo/codigo/7751271024679')->assertOk();
    expect($r->json('producto'))->toMatchArray([
        'nombre' => 'Leche Condensada Gloria', 'marca' => 'Gloria', 'presentacion' => '393g', 'categoria' => 'Lácteos', 'fuente' => 'openfoodfacts',
    ])->and($r->json('producto.foto'))->toContain('/api/v1/catalogo/');
    Storage::disk('local')->assertExists('catalogo/off-7751271024679.jpg');

    // La segunda vez sale del catálogo, sin volver a Open Food Facts.
    Http::fake();
    $this->getJson('/api/v1/catalogo/codigo/7751271024679')->assertOk();
    expect(CatalogoProducto::count())->toBe(1);
});

test('al escanear, también entra lo que no es de panadería (si lo escanean es porque lo venden), en "Otros"', function () {
    $this->getJson('/api/v1/catalogo/codigo/7750000000001')->assertOk()
        ->assertJson(['producto' => ['nombre' => 'Detergente en polvo', 'marca' => 'Marca X', 'categoria' => 'Otros', 'fuente' => 'openfoodfacts']]);
    expect(CatalogoProducto::where('codigo_barras', '7750000000001')->exists())->toBeTrue();
});

test('código que nadie tiene: 404 sin sugerencia; producto desactivado por el proveedor: solo su nombre', function () {
    $this->getJson('/api/v1/catalogo/codigo/7759999999999')->assertNotFound()
        ->assertJson(['error' => 'Este código no está registrado en ningún catálogo.', 'sugerencia' => null]);
    $this->getJson('/api/v1/catalogo/codigo/abc')->assertUnprocessable();

    CatalogoProducto::create(['codigo_barras' => '7750000000001', 'nombre' => 'Detergente', 'unidad' => 'NIU', 'activo' => false]);
    $this->getJson('/api/v1/catalogo/codigo/7750000000001')->assertNotFound()
        ->assertJson(['error' => 'Este producto no está disponible en el catálogo.', 'sugerencia' => ['nombre' => 'Detergente en polvo']]);
    expect(CatalogoProducto::count())->toBe(1);
});

test('la importación masiva solo trae productos de panadería', function () {
    $this->artisan('catalogo:openfoodfacts', ['--categoria' => ['en:milks']])
        ->expectsOutputToContain('Leche Condensada Gloria (Lácteos)')
        ->expectsOutputToContain('1 productos nuevos')
        ->assertSuccessful();
    expect(CatalogoProducto::pluck('codigo_barras')->all())->toBe(['7751271024679']);
});
