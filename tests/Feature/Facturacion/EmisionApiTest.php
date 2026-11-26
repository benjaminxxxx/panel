<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Sunat\ServicioSunat;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\EmpresaToken;
use App\Models\Serie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Facturacion;
use Tests\Support\ServicioSunatFalso;
use Tests\TestCase;

class EmisionApiTest extends TestCase
{
    use RefreshDatabase;

    private static ?string $certificadoPem = null;

    private ServicioSunatFalso $sunat;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->sunat = new ServicioSunatFalso;
        $this->app->instance(ServicioSunat::class, $this->sunat);
    }

    /**
     * @return array{0: Empresa, 1: string}
     */
    private function empresa(string $ruc = '20000000001', bool $conCertificado = true): array
    {
        $empresa = Empresa::create([
            'ruc' => $ruc,
            'razon_social' => "EMPRESA {$ruc} SAC",
            'direccion' => 'AV. PRUEBA 123',
            'ubigeo' => '150101',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
            'entorno' => Entorno::Beta,
        ]);

        if ($conCertificado) {
            self::$certificadoPem ??= Facturacion::certificadoAutofirmado()['pem'];
            $empresa->guardarCertificado(CertificadoDigital::cargar(self::$certificadoPem));
        }

        Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::Factura, 'serie' => 'F001']);
        Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::Boleta, 'serie' => 'B001']);

        [, $token] = EmpresaToken::generar($empresa, 'pos', 'test');

        return [$empresa, $token];
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function factura(array $cambios = []): array
    {
        return array_replace([
            'referencia_externa' => 'venta-'.uniqid(),
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'cliente' => [
                'tipo_documento' => '6',
                'numero_documento' => Facturacion::RUC_CLIENTE,
                'razon_social' => 'SUPERMERCADOS PERUANOS S.A.',
            ],
            'items' => [
                ['codigo' => 'P001', 'descripcion' => 'MARTILLO', 'cantidad' => 2, 'precio_unitario' => '59.00'],
                ['descripcion' => 'CABLE', 'unidad' => 'MTR', 'cantidad' => '1.5', 'precio_unitario' => 12.9],
            ],
        ], $cambios);
    }

    public function test_sin_token_o_token_invalido_responde_401(): void
    {
        $this->postJson('/api/v1/comprobantes', $this->factura())->assertUnauthorized();
        $this->withToken('fk_inventado')->postJson('/api/v1/comprobantes', $this->factura())->assertUnauthorized();
    }

    public function test_token_revocado_responde_401(): void
    {
        [$empresa, $token] = $this->empresa();
        $empresa->tokens()->update(['revocado_at' => now()]);

        $this->withToken($token)->getJson('/api/v1/yo')->assertUnauthorized();
    }

    public function test_emite_factura_aceptada(): void
    {
        [$empresa, $token] = $this->empresa();

        $respuesta = $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['referencia_externa' => 'venta-1']));

        $respuesta->assertCreated()
            ->assertJsonPath('numero', 'F001-1')
            ->assertJsonPath('estado', 'aceptado')
            ->assertJsonPath('valido', true)
            ->assertJsonPath('sistema', 'pos')
            ->assertJsonPath('hash', 'HASH-1')
            ->assertJsonPath('totales.total', '137.35')
            ->assertJsonPath('totales.op_gravadas', '116.40')
            ->assertJsonPath('totales.igv', '20.95')
            ->assertJsonPath('totales.monto_letras', 'SON CIENTO TREINTA Y SIETE CON 35/100 SOLES')
            ->assertJsonPath('items.1.cantidad', '1.500000')
            ->assertJsonPath('items.1.unidad', 'MTR');

        $comprobante = Comprobante::firstOrFail();
        Storage::disk('local')->assertExists($comprobante->xml_path);
        Storage::disk('local')->assertExists($comprobante->cdr_path);
        $this->assertStringStartsWith('facturacion/20000000001/', $comprobante->xml_path);
        $this->assertSame(1, $empresa->series()->where('serie', 'F001')->value('correlativo_beta'));

        $this->withToken($token)->get("/api/v1/comprobantes/{$comprobante->uuid}/xml")
            ->assertOk()
            ->assertDownload('20000000001-01-F001-1.xml');
    }

    public function test_fecha_de_emision_se_conserva_en_hora_de_lima(): void
    {
        [, $token] = $this->empresa();
        $fecha = now('America/Lima')->subHour()->startOfMinute();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['fecha_emision' => $fecha->toIso8601String()]))
            ->assertCreated()
            ->assertJsonPath('fecha_emision', $fecha->toIso8601String());

        // Aunque la app esté en UTC, se guarda el instante correcto.
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
        $fechaUtc = now('America/Lima')->subMinutes(30)->startOfMinute();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['fecha_emision' => $fechaUtc->toIso8601String()]))
            ->assertJsonPath('fecha_emision', $fechaUtc->toIso8601String());
    }

    public function test_items_con_importe_fijado_suman_exactamente(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura([
            'items' => [
                ['descripcion' => 'MEDIAS X3', 'cantidad' => 3, 'importe' => '100.00'],
                ['descripcion' => 'BOLSA', 'cantidad' => 1, 'importe' => 0.10],
                ['descripcion' => 'POLO', 'cantidad' => 1, 'importe' => '23.39'],
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('totales.total', '123.49')
            ->assertJsonPath('items.0.importe_total', '100.00')
            ->assertJsonPath('items.0.precio_unitario', '33.3333333333')
            ->assertJsonPath('items.1.importe_total', '0.10')
            ->assertJsonPath('items.2.valor_venta', '19.82');

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura([
            'items' => [['descripcion' => 'X', 'cantidad' => 1, 'importe' => '10.005']],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.importe');
    }

    public function test_correlativos_consecutivos_por_serie(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura())->assertJsonPath('numero', 'F001-1');
        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura())->assertJsonPath('numero', 'F001-2');
        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['tipo_comprobante' => '03', 'serie' => 'B001']))
            ->assertJsonPath('numero', 'B001-1');
    }

    public function test_misma_referencia_es_idempotente(): void
    {
        [, $token] = $this->empresa();
        $datos = $this->factura(['referencia_externa' => 'venta-99']);

        $primera = $this->withToken($token)->postJson('/api/v1/comprobantes', $datos)->assertCreated();
        $segunda = $this->withToken($token)->postJson('/api/v1/comprobantes', $datos)->assertOk();

        $this->assertSame($primera->json('id'), $segunda->json('id'));
        $this->assertSame(1, Comprobante::count());
        $this->assertSame(1, $this->sunat->envios);
    }

    public function test_error_de_validacion_no_consume_correlativo(): void
    {
        [$empresa, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura([
            'cliente' => ['tipo_documento' => '1', 'numero_documento' => '45678912', 'razon_social' => 'JUAN'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('cliente.tipo_documento');

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura([
            'cliente' => ['tipo_documento' => '6', 'numero_documento' => '20100070971', 'razon_social' => 'X'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('cliente.numero_documento');

        $this->assertSame(0, $empresa->series()->where('serie', 'F001')->value('correlativo_beta'));
        $this->assertSame(0, Comprobante::count());
    }

    public function test_factura_sin_cliente_es_rechazada_por_la_api(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['cliente' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cliente');
    }

    public function test_boleta_sin_cliente_usa_clientes_varios(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['tipo_comprobante' => '03', 'serie' => 'B001', 'cliente' => null]))
            ->assertCreated()
            ->assertJsonPath('cliente.tipo_documento', '-')
            ->assertJsonPath('cliente.razon_social', 'CLIENTES VARIOS');
    }

    public function test_boleta_mayor_a_700_sin_cliente(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura([
            'tipo_comprobante' => '03', 'serie' => 'B001', 'cliente' => null,
            'items' => [['descripcion' => 'TALADRO', 'cantidad' => 1, 'precio_unitario' => '850.00']],
        ]))->assertUnprocessable()->assertJsonValidationErrors('cliente');
    }

    public function test_serie_no_registrada(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['serie' => 'F999']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('serie');
    }

    public function test_empresa_sin_certificado(): void
    {
        [, $token] = $this->empresa(conCertificado: false);

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('empresa');
    }

    public function test_si_falla_la_firma_no_se_pierde_el_correlativo(): void
    {
        [$empresa, $token] = $this->empresa();
        $this->sunat->fallarFirma = true;

        $this->withoutExceptionHandling();
        try {
            $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura());
            $this->fail('Debió lanzar excepción');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $empresa->series()->where('serie', 'F001')->value('correlativo_beta'));
        $this->assertSame(0, Comprobante::count());
    }

    public function test_rechazo_de_sunat(): void
    {
        [, $token] = $this->empresa();
        $this->sunat->responder(EstadoComprobante::Rechazado, '2800', 'Tipo de documento no permitido');

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura())
            ->assertCreated()
            ->assertJsonPath('estado', 'rechazado')
            ->assertJsonPath('valido', false)
            ->assertJsonPath('sunat.codigo', '2800');
    }

    public function test_error_de_envio_y_reenvio(): void
    {
        [, $token] = $this->empresa();
        $this->sunat->responder(EstadoComprobante::ErrorEnvio, 'HTTP', 'Unauthorized');

        $id = $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura())
            ->assertCreated()
            ->assertJsonPath('estado', 'error_envio')
            ->json('id');

        $this->sunat->aceptar();

        $this->withToken($token)->postJson("/api/v1/comprobantes/{$id}/reenviar")
            ->assertOk()
            ->assertJsonPath('estado', 'aceptado')
            ->assertJsonPath('numero', 'F001-1')
            ->assertJsonPath('intentos_envio', 2);

        // Un comprobante con respuesta definitiva no se reenvía.
        $this->withToken($token)->postJson("/api/v1/comprobantes/{$id}/reenviar")->assertUnprocessable();
    }

    public function test_comando_reenviar_procesa_pendientes(): void
    {
        [, $token] = $this->empresa();
        $this->sunat->responder(EstadoComprobante::ErrorEnvio, 'HTTP', 'timeout');
        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura())->assertCreated();

        $this->sunat->aceptar();
        $this->travel(10)->minutes();

        $this->artisan('facturacion:reenviar')->assertSuccessful();

        $this->assertSame(EstadoComprobante::Aceptado, Comprobante::firstOrFail()->estado);
    }

    public function test_una_empresa_no_ve_comprobantes_de_otra(): void
    {
        [, $tokenA] = $this->empresa('20000000001');
        [, $tokenB] = $this->empresa(Facturacion::RUC_CLIENTE);

        $id = $this->withToken($tokenA)->postJson('/api/v1/comprobantes', $this->factura())->json('id');

        $this->withToken($tokenB)->getJson("/api/v1/comprobantes/{$id}")->assertNotFound();
        $this->withToken($tokenB)->get("/api/v1/comprobantes/{$id}/xml")->assertNotFound();
        $this->withToken($tokenA)->getJson("/api/v1/comprobantes/{$id}")->assertOk();
    }

    public function test_enlace_publico_firmado_al_pdf_para_compartir(): void
    {
        [, $token] = $this->empresa();
        $enlace = $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura())->json('archivos.publico');

        // Sin token ni sesión: basta el enlace firmado (el cliente lo abre desde WhatsApp).
        $this->assertStringContainsString('signature=', $enlace);
        $this->get($enlace)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Cambiar el comprobante o la firma no abre nada.
        $otro = preg_replace('#/c/[^?]+#', '/c/'.Comprobante::firstOrFail()->uuid.'x', $enlace);
        $this->get($otro)->assertNotFound();
        $this->get(strtok($enlace, '?'))->assertForbidden();
    }

    public function test_yo_muestra_la_empresa_vinculada(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->getJson('/api/v1/yo')
            ->assertOk()
            ->assertJsonPath('sistema', 'pos')
            ->assertJsonPath('empresa.ruc', '20000000001')
            ->assertJsonPath('empresa.entorno', 'beta')
            ->assertJsonCount(2, 'series');
    }

    public function test_la_informacion_adicional_se_guarda_para_imprimirla_en_su_zona(): void
    {
        [, $token] = $this->empresa();
        $datos = [
            ['ubicacion' => 'cabecera', 'etiqueta' => 'Teléfono', 'valor' => '999 888 777'],
            ['ubicacion' => 'pie', 'etiqueta' => null, 'valor' => 'BCP soles 191-1234567-0-12'],
        ];

        $id = $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['informacion_adicional' => $datos]))
            ->assertCreated()
            ->assertJsonPath('informacion_adicional.1.valor', 'BCP soles 191-1234567-0-12')
            ->json('id');

        $comprobante = Comprobante::where('uuid', $id)->firstOrFail();
        $this->assertSame('999 888 777', $comprobante->informacionEn('cabecera')[0]['valor']);
        $this->assertSame([], $comprobante->informacionEn('centro'));

        $this->withToken($token)->get("/api/v1/comprobantes/{$id}/pdf")->assertOk();
        $this->withToken($token)->get("/api/v1/comprobantes/{$id}/pdf?formato=ticket")->assertOk();

        $this->withToken($token)->postJson('/api/v1/comprobantes', $this->factura(['informacion_adicional' => [['ubicacion' => 'lateral', 'valor' => 'x']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('informacion_adicional.0.ubicacion');
    }

    public function test_secretos_se_guardan_cifrados(): void
    {
        [$empresa] = $this->empresa();
        $empresa->update(['sol_clave' => 'MiClaveSol']);

        $crudo = \DB::table('empresas')->where('id', $empresa->id)->first();

        $this->assertStringNotContainsString('MiClaveSol', (string) $crudo->sol_clave);
        $this->assertStringNotContainsString('PRIVATE KEY', (string) $crudo->certificado_pem);
        $this->assertSame('MiClaveSol', $empresa->fresh()?->sol_clave);
    }
}
