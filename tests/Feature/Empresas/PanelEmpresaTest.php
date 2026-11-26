<?php

namespace Tests\Feature\Empresas;

use App\Facturacion\Core\Certificado\CertificadoDigital;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Sunat\ServicioSunat;
use App\Livewire\Empresas\ConexionSunat;
use App\Livewire\Empresas\Formulario;
use App\Livewire\Empresas\Series;
use App\Livewire\Empresas\Tokens;
use App\Models\Empresa;
use App\Models\EmpresaToken;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Facturacion;
use Tests\Support\ServicioSunatFalso;
use Tests\TestCase;

class PanelEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private static ?array $certificado = null;

    /**
     * @return array{pem: string, pfx: string, clave: string}
     */
    private function certificado(): array
    {
        return self::$certificado ??= Facturacion::certificadoAutofirmado(Facturacion::RUC_CLIENTE);
    }

    private function empresaDe(User $usuario, string $ruc = Facturacion::RUC_CLIENTE): Empresa
    {
        return Empresa::create([
            'user_id' => $usuario->id,
            'ruc' => $ruc,
            'razon_social' => 'FERRETERIA EL MARTILLO S.A.C.',
            'direccion' => 'AV. PRUEBA 123',
            'ubigeo' => '150101',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
            'entorno' => Entorno::Beta,
        ]);
    }

    public function test_registro_por_correo_exige_verificar_antes_de_usar_el_panel(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Luis', 'email' => 'luis@correo.pe', 'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('empresas.create'))->assertRedirect(route('verification.notice'));
    }

    public function test_panel_en_espanol(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis empresas')
            ->assertSee('Registrar empresa');
    }

    public function test_registra_empresa(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('ruc', Facturacion::RUC_CLIENTE)
            ->set('razon_social', 'ferreteria el martillo sac')
            ->set('direccion', 'av. los olivos 123')
            ->set('ubigeo', '150117')
            ->set('departamento', 'Lima')
            ->set('provincia', 'Lima')
            ->set('distrito', 'Los Olivos')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect();

        $empresa = Empresa::firstOrFail();
        $this->assertSame($usuario->id, $empresa->user_id);
        $this->assertSame('FERRETERIA EL MARTILLO SAC', $empresa->razon_social);
        $this->assertSame(Entorno::Beta, $empresa->entorno);
        $this->assertSame($usuario->email, $empresa->email);
    }

    private function formularioCompleto(User $usuario): Testable
    {
        return Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('ruc', Facturacion::RUC_CLIENTE)
            ->set('razon_social', 'FERRETERIA EL MARTILLO SAC')
            ->set('direccion', 'AV. LOS OLIVOS 123')
            ->set('ubigeo', '150117')
            ->set('departamento', 'LIMA')
            ->set('provincia', 'LIMA')
            ->set('distrito', 'LOS OLIVOS');
    }

    public function test_registro_con_certificado_logo_y_modo_produccion(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->create();

        $this->formularioCompleto($usuario)
            ->set('entorno', 'produccion')
            ->set('certificado', UploadedFile::fake()->createWithContent('cert.pfx', $this->certificado()['pfx']))
            ->set('clave_certificado', $this->certificado()['clave'])
            ->set('sol_usuario', 'factura1')
            ->set('sol_clave', 'Clave123')
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 120))
            ->call('guardar')
            ->assertHasNoErrors();

        $empresa = Empresa::firstOrFail();
        $this->assertSame(Entorno::Produccion, $empresa->entorno);
        $this->assertNotNull($empresa->certificado_vence_en);
        $this->assertSame('FACTURA1', $empresa->sol_usuario);
        $this->assertNotNull($empresa->logo_path);
        Storage::disk('local')->assertExists($empresa->logo_path);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $empresa->logoDataUri());
    }

    public function test_registro_en_produccion_sin_certificado_no_crea_la_empresa(): void
    {
        $usuario = User::factory()->create();

        $this->formularioCompleto($usuario)
            ->set('entorno', 'produccion')
            ->call('guardar')
            ->assertHasErrors('certificado');

        // Con certificado pero sin clave SOL: falla y no queda la empresa a medias.
        $this->formularioCompleto($usuario)
            ->set('entorno', 'produccion')
            ->set('certificado', UploadedFile::fake()->createWithContent('cert.pem', $this->certificado()['pem']))
            ->call('guardar')
            ->assertHasErrors('sol_usuario');

        $this->assertSame(0, Empresa::count());
    }

    public function test_registro_en_modo_prueba_sin_certificado(): void
    {
        $usuario = User::factory()->create();

        $this->formularioCompleto($usuario)->call('guardar')->assertHasNoErrors();

        $this->assertSame(Entorno::Beta, Empresa::firstOrFail()->entorno);
    }

    public function test_logo_invalido_y_quitar_logo(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->create();

        $this->formularioCompleto($usuario)
            ->set('logo', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))
            ->call('guardar')
            ->assertHasErrors('logo');

        $this->formularioCompleto($usuario)->set('logo', UploadedFile::fake()->image('logo.jpg'))->call('guardar');
        $empresa = Empresa::firstOrFail();
        $ruta = (string) $empresa->logo_path;

        Livewire::actingAs($usuario)->test(Formulario::class, ['empresa' => $empresa])
            ->assertSet('logoActual', fn ($v) => str_starts_with((string) $v, 'data:image/'))
            ->set('quitarLogo', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull($empresa->fresh()?->logo_path);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_valida_ruc(): void
    {
        $usuario = User::factory()->create();
        $this->empresaDe(User::factory()->create(), '20000000001');

        $formulario = fn (string $ruc) => Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('ruc', $ruc)->set('razon_social', 'X')->set('direccion', 'X')->set('ubigeo', '150101')
            ->set('departamento', 'X')->set('provincia', 'X')->set('distrito', 'X')
            ->call('guardar');

        $formulario('20100070971')->assertHasErrors('ruc');
        $formulario('20000000001')->assertHasErrors(['ruc' => 'unique']);
    }

    public function test_no_puede_ver_ni_editar_empresas_ajenas(): void
    {
        $ajena = $this->empresaDe(User::factory()->create());
        $intruso = User::factory()->create();

        $this->actingAs($intruso)->get(route('empresas.show', $ajena))->assertForbidden();
        $this->actingAs($intruso)->get(route('empresas.edit', $ajena))->assertForbidden();
        Livewire::actingAs($intruso)->test(Tokens::class, ['empresa' => $ajena])->assertForbidden();
    }

    public function test_pagina_de_empresa_muestra_pasos_pendientes(): void
    {
        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);

        $this->actingAs($usuario)->get(route('empresas.show', $empresa))
            ->assertOk()
            ->assertSee('Aún no cargado.')
            ->assertSee('Registre al menos una serie');
    }

    public function test_carga_certificado_pfx(): void
    {
        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);
        $archivo = UploadedFile::fake()->createWithContent('certificado.pfx', $this->certificado()['pfx']);

        Livewire::actingAs($usuario)->test(ConexionSunat::class, ['empresa' => $empresa])
            ->set('certificado', $archivo)
            ->set('clave_certificado', $this->certificado()['clave'])
            ->call('subirCertificado')
            ->assertHasNoErrors()
            ->assertDispatched('empresa-actualizada')
            ->assertSet('clave_certificado', '');

        $this->assertNotNull($empresa->fresh()?->certificado_vence_en);
        $this->assertStringContainsString('PRIVATE KEY', (string) $empresa->fresh()?->certificado_pem);
    }

    public function test_certificado_con_clave_incorrecta(): void
    {
        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);

        Livewire::actingAs($usuario)->test(ConexionSunat::class, ['empresa' => $empresa])
            ->set('certificado', UploadedFile::fake()->createWithContent('certificado.pfx', $this->certificado()['pfx']))
            ->set('clave_certificado', 'equivocada')
            ->call('subirCertificado')
            ->assertHasErrors('certificado');

        $this->assertNull($empresa->fresh()?->certificado_pem);
    }

    public function test_certificado_de_otro_ruc_es_rechazado(): void
    {
        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);
        $ajeno = Facturacion::certificadoAutofirmado('20000000001');

        Livewire::actingAs($usuario)->test(ConexionSunat::class, ['empresa' => $empresa])
            ->set('certificado', UploadedFile::fake()->createWithContent('otro.pem', $ajeno['pem']))
            ->call('subirCertificado')
            ->assertHasErrors('certificado');
    }

    public function test_produccion_exige_certificado_y_clave_sol(): void
    {
        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);

        $componente = Livewire::actingAs($usuario)->test(ConexionSunat::class, ['empresa' => $empresa])
            ->set('entorno', 'produccion')
            ->call('guardarSunat')
            ->assertHasErrors('entorno');

        $componente->set('certificado', UploadedFile::fake()->createWithContent('c.pem', $this->certificado()['pem']))
            ->call('subirCertificado')
            ->set('entorno', 'produccion')
            ->call('guardarSunat')
            ->assertHasErrors('sol_usuario');

        $componente->set('sol_usuario', 'factura1')->set('sol_clave', 'Clave123')->call('guardarSunat')->assertHasNoErrors();

        $empresa->refresh();
        $this->assertSame(Entorno::Produccion, $empresa->entorno);
        $this->assertSame('FACTURA1', $empresa->sol_usuario);
        $this->assertSame('Clave123', $empresa->sol_clave);

        // Guardar sin reescribir la clave la conserva.
        $componente->call('guardarSunat');
        $this->assertSame('Clave123', $empresa->fresh()?->sol_clave);
    }

    public function test_series(): void
    {
        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);

        Livewire::actingAs($usuario)->test(Series::class, ['empresa' => $empresa])
            ->set('tipo', '03')
            ->assertSet('serie', 'B001')
            ->set('ultimo_correlativo', 1520)
            ->call('agregar')
            ->assertHasNoErrors()
            ->call('agregar')
            ->assertHasErrors(['serie' => 'unique'])
            ->set('tipo', '01')
            ->set('serie', 'B002')
            ->call('agregar')
            ->assertHasErrors(['serie' => 'regex']);

        $serie = $empresa->series()->firstOrFail();
        $this->assertSame(TipoComprobante::Boleta, $serie->tipo_comprobante);
        $this->assertSame(1520, $serie->correlativo);
    }

    public function test_token_generado_en_el_panel_funciona_en_la_api_y_se_puede_revocar(): void
    {
        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);

        $componente = Livewire::actingAs($usuario)->test(Tokens::class, ['empresa' => $empresa])
            ->set('sistema', 'hotel')
            ->set('nombre', 'Hotel Cusco')
            ->call('crear')
            ->assertHasNoErrors();

        $token = (string) $componente->get('tokenNuevo');
        $this->assertStringStartsWith('fk_', $token);

        $this->withToken($token)->getJson('/api/v1/yo')
            ->assertOk()
            ->assertJsonPath('sistema', 'hotel')
            ->assertJsonPath('empresa.ruc', $empresa->ruc);

        $componente->call('cerrarToken')->assertSet('tokenNuevo', null);
        $this->assertNotSame($token, $empresa->tokens()->first()?->token_hash, 'Solo se guarda el hash.');

        $componente->call('revocar', $empresa->tokens()->firstOrFail()->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/yo')->assertUnauthorized();
    }

    public function test_lista_comprobantes_y_descarga_xml_desde_el_panel(): void
    {
        Storage::fake('local');
        $this->app->instance(ServicioSunat::class, new ServicioSunatFalso);

        $usuario = User::factory()->create();
        $empresa = $this->empresaDe($usuario);
        $empresa->guardarCertificado(CertificadoDigital::cargar($this->certificado()['pem']));
        Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::Boleta, 'serie' => 'B001']);
        [, $token] = EmpresaToken::generar($empresa, 'pos', 'test');

        $this->withToken($token)->postJson('/api/v1/comprobantes', [
            'referencia_externa' => 'v1', 'tipo_comprobante' => '03', 'serie' => 'B001',
            'items' => [['descripcion' => 'POLO', 'cantidad' => 1, 'precio_unitario' => '35.00']],
        ])->assertCreated();

        $comprobante = $empresa->comprobantes()->firstOrFail();

        $this->actingAs($usuario)->get(route('empresas.show', $empresa))
            ->assertOk()
            ->assertSee('B001-1')
            ->assertSee('S/ 35.00')
            ->assertSee('aceptado');

        $this->actingAs($usuario)->get(route('empresas.comprobantes.archivo', [$empresa, $comprobante, 'xml']))
            ->assertOk()
            ->assertDownload("{$empresa->ruc}-03-B001-1.xml");

        foreach (['a4', 'ticket'] as $formato) {
            $pdf = $this->actingAs($usuario)->get(route('empresas.comprobantes.archivo', [$empresa, $comprobante, $formato]));
            $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        }

        // API: el sistema conectado también obtiene el PDF.
        $this->withToken($token)->get("/api/v1/comprobantes/{$comprobante->uuid}/pdf?formato=ticket")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->withToken($token)->get("/api/v1/comprobantes/{$comprobante->uuid}/pdf?formato=carta")
            ->assertStatus(422);

        $this->actingAs(User::factory()->create())
            ->get(route('empresas.comprobantes.archivo', [$empresa, $comprobante, 'xml']))
            ->assertForbidden();
    }

    public function test_sistema_invalido(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)->test(Tokens::class, ['empresa' => $this->empresaDe($usuario)])
            ->set('sistema', 'inventado')
            ->call('crear')
            ->assertHasErrors('sistema');
    }
}
