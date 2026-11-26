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
use Illuminate\Testing\TestResponse;
use Tests\Support\Facturacion;
use Tests\Support\ServicioSunatFalso;
use Tests\TestCase;

class NotaCreditoApiTest extends TestCase
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
     * Empresa con series de factura, boleta y notas de crédito de ambas.
     *
     * @return array{0: Empresa, 1: string}
     */
    private function empresa(string $ruc = '20000000001', bool $conSeriesDeNotas = true): array
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

        self::$certificadoPem ??= Facturacion::certificadoAutofirmado()['pem'];
        $empresa->guardarCertificado(CertificadoDigital::cargar(self::$certificadoPem));

        Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::Factura, 'serie' => 'F001']);
        Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::Boleta, 'serie' => 'B001']);

        if ($conSeriesDeNotas) {
            Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::NotaCredito, 'serie' => 'FC01']);
            Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::NotaCredito, 'serie' => 'BC01']);
        }

        [, $token] = EmpresaToken::generar($empresa, 'pos', 'test');

        return [$empresa, $token];
    }

    /**
     * Factura aceptada de S/ 137.35: 2 martillos a S/ 59.00 y 1.5 m de cable por S/ 19.35.
     */
    private function factura(string $token, string $referencia = 'venta-1'): string
    {
        return $this->withToken($token)->postJson('/api/v1/comprobantes', [
            'referencia_externa' => $referencia,
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'cliente' => ['tipo_documento' => '6', 'numero_documento' => Facturacion::RUC_CLIENTE, 'razon_social' => 'SUPERMERCADOS PERUANOS S.A.', 'direccion' => 'AV. X 123'],
            'items' => [
                ['codigo' => 'P001', 'descripcion' => 'MARTILLO', 'cantidad' => 2, 'importe' => '118.00'],
                ['descripcion' => 'CABLE', 'unidad' => 'MTR', 'cantidad' => '1.5', 'importe' => '19.35'],
            ],
        ])->assertCreated()->json('id');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function notaCredito(string $token, string $comprobanteId, array $datos = []): TestResponse
    {
        return $this->withToken($token)->postJson("/api/v1/comprobantes/{$comprobanteId}/nota-credito", ['referencia_externa' => 'anulacion-1', ...$datos]);
    }

    public function test_anula_una_factura_completa_con_su_mismo_cliente_items_y_total(): void
    {
        [$empresa, $token] = $this->empresa();
        $facturaId = $this->factura($token);

        $respuesta = $this->notaCredito($token, $facturaId, ['motivo' => 'El cliente desistió de la compra']);

        $respuesta->assertCreated()
            ->assertJsonPath('tipo_comprobante', '07')
            ->assertJsonPath('numero', 'FC01-1')
            ->assertJsonPath('estado', 'aceptado')
            ->assertJsonPath('comprobante_afectado.id', $facturaId)
            ->assertJsonPath('comprobante_afectado.numero', 'F001-1')
            ->assertJsonPath('motivo.codigo', '01')
            ->assertJsonPath('motivo.descripcion', 'El cliente desistió de la compra')
            ->assertJsonPath('cliente.numero_documento', Facturacion::RUC_CLIENTE)
            ->assertJsonPath('totales.total', '137.35')
            ->assertJsonPath('items.0.importe_total', '118.00')
            ->assertJsonPath('items.1.cantidad', '1.500000')
            ->assertJsonPath('items.1.unidad', 'MTR');

        $documento = $this->sunat->firmados[1];
        $this->assertSame(TipoComprobante::NotaCredito, $documento->tipo);
        $this->assertSame('F001-1', $documento->afectado?->numero());
        $this->assertSame(TipoComprobante::Factura, $documento->afectado?->tipo);

        $nota = Comprobante::where('tipo_comprobante', '07')->firstOrFail();
        Storage::disk('local')->assertExists($nota->xml_path);
        $this->assertSame('20000000001-07-FC01-1', $nota->nombreArchivo());
        $this->assertSame(1, $empresa->series()->where('serie', 'FC01')->value('correlativo_beta'));
        $this->assertSame(1, $empresa->series()->where('serie', 'F001')->value('correlativo_beta'));
    }

    public function test_la_nota_de_una_boleta_usa_la_serie_de_boletas(): void
    {
        [, $token] = $this->empresa();
        $boletaId = $this->withToken($token)->postJson('/api/v1/comprobantes', [
            'referencia_externa' => 'venta-b',
            'tipo_comprobante' => '03',
            'serie' => 'B001',
            'items' => [['descripcion' => 'CLAVOS', 'cantidad' => 1, 'importe' => '10.00']],
        ])->assertCreated()->json('id');

        $this->notaCredito($token, $boletaId)->assertCreated()
            ->assertJsonPath('numero', 'BC01-1')
            ->assertJsonPath('motivo.descripcion', 'Anulación de la operación');

        $this->notaCredito($token, $boletaId, ['referencia_externa' => 'otra', 'serie' => 'FC01'])
            ->assertUnprocessable()->assertJsonValidationErrors('serie');
    }

    public function test_reintentar_con_la_misma_referencia_no_emite_otra_nota(): void
    {
        [$empresa, $token] = $this->empresa();
        $facturaId = $this->factura($token);

        $primera = $this->notaCredito($token, $facturaId)->assertCreated()->json('id');
        $segunda = $this->notaCredito($token, $facturaId)->assertOk()->json('id');

        $this->assertSame($primera, $segunda);
        $this->assertSame(1, Comprobante::where('tipo_comprobante', '07')->count());
        $this->assertSame(1, $empresa->series()->where('serie', 'FC01')->value('correlativo_beta'));
    }

    public function test_un_comprobante_no_se_acredita_por_mas_de_su_total(): void
    {
        [$empresa, $token] = $this->empresa();
        $facturaId = $this->factura($token);
        $this->notaCredito($token, $facturaId)->assertCreated();

        $this->notaCredito($token, $facturaId, ['referencia_externa' => 'anulacion-2'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comprobante');

        $this->assertSame(1, $empresa->series()->where('serie', 'FC01')->value('correlativo_beta'));
    }

    public function test_devolucion_por_item_acredita_solo_lo_devuelto_y_respeta_el_saldo(): void
    {
        [, $token] = $this->empresa();
        $facturaId = $this->factura($token);
        $martillo = ['items' => [['codigo' => 'P001', 'descripcion' => 'MARTILLO', 'cantidad' => 1, 'importe' => '59.00']]];

        $this->notaCredito($token, $facturaId, ['motivo_codigo' => '07', ...$martillo])
            ->assertCreated()
            ->assertJsonPath('totales.total', '59.00')
            ->assertJsonPath('motivo.descripcion', 'Devolución por ítem');

        // Quedan S/ 78.35 por acreditar: otra devolución de S/ 59.00 entra, una tercera ya no.
        $this->notaCredito($token, $facturaId, ['referencia_externa' => 'dev-2', 'motivo_codigo' => '07', ...$martillo])->assertCreated();
        $this->notaCredito($token, $facturaId, ['referencia_externa' => 'dev-3', 'motivo_codigo' => '07', ...$martillo])
            ->assertUnprocessable()->assertJsonValidationErrors('comprobante');

        // Y tampoco se puede anular completa una factura que ya tiene devoluciones.
        $this->notaCredito($token, $facturaId, ['referencia_externa' => 'anular'])->assertUnprocessable();
    }

    public function test_el_motivo_decide_si_la_nota_lleva_items(): void
    {
        [, $token] = $this->empresa();
        $facturaId = $this->factura($token);
        $items = [['descripcion' => 'MARTILLO', 'cantidad' => 1, 'importe' => '59.00']];

        $this->notaCredito($token, $facturaId, ['motivo_codigo' => '07'])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->notaCredito($token, $facturaId, ['motivo_codigo' => '01', 'items' => $items])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->notaCredito($token, $facturaId, ['motivo_codigo' => '99'])->assertUnprocessable()->assertJsonValidationErrors('motivo_codigo');

        $this->assertSame(0, Comprobante::where('tipo_comprobante', '07')->count());
    }

    public function test_una_nota_rechazada_por_sunat_no_consume_el_saldo(): void
    {
        [, $token] = $this->empresa();
        $facturaId = $this->factura($token);

        $this->sunat->responder(EstadoComprobante::Rechazado, '2800', 'Dato no válido');
        $this->notaCredito($token, $facturaId)->assertCreated()->assertJsonPath('estado', 'rechazado');

        $this->sunat->aceptar();
        $this->notaCredito($token, $facturaId, ['referencia_externa' => 'anulacion-2'])
            ->assertCreated()
            ->assertJsonPath('numero', 'FC01-2')
            ->assertJsonPath('estado', 'aceptado');
    }

    public function test_solo_se_acreditan_facturas_y_boletas_validas_de_la_propia_empresa(): void
    {
        [, $token] = $this->empresa();
        [, $tokenAjeno] = $this->empresa('20100070970');

        $this->sunat->responder(EstadoComprobante::Rechazado, '2800', 'Dato no válido');
        $rechazada = $this->factura($token, 'venta-rechazada');
        $this->sunat->aceptar();
        $facturaId = $this->factura($token);
        $notaId = $this->notaCredito($token, $facturaId)->assertCreated()->json('id');

        $this->notaCredito($token, $rechazada, ['referencia_externa' => 'r-1'])->assertUnprocessable()->assertJsonValidationErrors('comprobante');
        $this->notaCredito($token, $notaId, ['referencia_externa' => 'r-2'])->assertUnprocessable()->assertJsonValidationErrors('comprobante');
        $this->notaCredito($tokenAjeno, $facturaId, ['referencia_externa' => 'r-3'])->assertNotFound();
    }

    public function test_sin_serie_de_notas_de_credito_se_explica_como_registrarla(): void
    {
        [, $token] = $this->empresa(conSeriesDeNotas: false);
        $facturaId = $this->factura($token);

        $this->notaCredito($token, $facturaId)
            ->assertUnprocessable()
            ->assertJsonPath('errors.serie.0', 'La empresa no tiene una serie de nota de crédito para Factura (debe empezar con F, ej: FC01). Regístrela en el panel → Series.');
    }

    public function test_una_nota_sin_respuesta_de_sunat_se_reenvia_con_el_mismo_xml(): void
    {
        [, $token] = $this->empresa();
        $facturaId = $this->factura($token);

        $this->sunat->responder(EstadoComprobante::ErrorEnvio, '0109', 'El servicio no está disponible');
        $notaId = $this->notaCredito($token, $facturaId)->assertCreated()->assertJsonPath('estado', 'error_envio')->json('id');

        $this->sunat->aceptar();
        $this->withToken($token)->postJson("/api/v1/comprobantes/{$notaId}/reenviar")->assertOk()->assertJsonPath('estado', 'aceptado');

        $this->assertSame(1, Comprobante::where('tipo_comprobante', '07')->count());
    }

    public function test_la_emision_directa_no_acepta_notas_de_credito(): void
    {
        [, $token] = $this->empresa();

        $this->withToken($token)->postJson('/api/v1/comprobantes', [
            'referencia_externa' => 'x',
            'tipo_comprobante' => '07',
            'serie' => 'FC01',
            'items' => [['descripcion' => 'X', 'cantidad' => 1, 'importe' => '10.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('tipo_comprobante');
    }

    public function test_la_nota_tiene_su_pdf_en_a4_y_ticket(): void
    {
        [, $token] = $this->empresa();
        $notaId = $this->notaCredito($token, $this->factura($token))->assertCreated()->json('id');

        $this->withToken($token)->get("/api/v1/comprobantes/{$notaId}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->withToken($token)->get("/api/v1/comprobantes/{$notaId}/pdf?formato=ticket")->assertOk();
    }

    public function test_una_disminucion_en_el_valor_acredita_solo_el_monto_rebajado(): void
    {
        [, $token] = $this->empresa();
        $facturaId = $this->factura($token);
        $rebaja = ['items' => [['descripcion' => 'DISMINUCIÓN EN EL VALOR', 'cantidad' => 1, 'importe' => '17.35']]];

        $this->notaCredito($token, $facturaId, ['motivo_codigo' => '09', ...$rebaja])
            ->assertCreated()
            ->assertJsonPath('totales.total', '17.35')
            ->assertJsonPath('motivo.codigo', '09')
            ->assertJsonPath('motivo.descripcion', 'Disminución en el valor');

        // Un descuento global también es parcial y exige ítems.
        $this->notaCredito($token, $facturaId, ['referencia_externa' => 'desc-1', 'motivo_codigo' => '04'])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->notaCredito($token, $facturaId, ['referencia_externa' => 'desc-1', 'motivo_codigo' => '04', ...$rebaja])->assertCreated();
    }

    public function test_una_boleta_no_admite_notas_de_credito_por_descuento(): void
    {
        [, $token] = $this->empresa();
        $boletaId = $this->withToken($token)->postJson('/api/v1/comprobantes', [
            'referencia_externa' => 'venta-b',
            'tipo_comprobante' => '03',
            'serie' => 'B001',
            'items' => [['descripcion' => 'CLAVOS', 'cantidad' => 1, 'importe' => '50.00']],
        ])->assertCreated()->json('id');
        $rebaja = ['items' => [['descripcion' => 'REBAJA', 'cantidad' => 1, 'importe' => '5.00']]];

        $this->notaCredito($token, $boletaId, ['motivo_codigo' => '04', ...$rebaja])->assertUnprocessable()->assertJsonValidationErrors('motivo_codigo');
        $this->notaCredito($token, $boletaId, ['motivo_codigo' => '09', ...$rebaja])->assertCreated()->assertJsonPath('numero', 'BC01-1');
    }

    // ---------------- Notas de débito ----------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function notaDebito(string $token, string $comprobanteId, array $datos = []): TestResponse
    {
        return $this->withToken($token)->postJson("/api/v1/comprobantes/{$comprobanteId}/nota-debito", [
            'referencia_externa' => 'aumento-1',
            'items' => [['descripcion' => 'FLETE NO FACTURADO', 'cantidad' => 1, 'importe' => '23.60']],
            ...$datos,
        ]);
    }

    public function test_una_nota_de_debito_aumenta_lo_cobrado_en_la_factura(): void
    {
        [$empresa, $token] = $this->empresa();
        Serie::create(['empresa_id' => $empresa->id, 'tipo_comprobante' => TipoComprobante::NotaDebito, 'serie' => 'FD01']);
        $facturaId = $this->factura($token);

        $this->notaDebito($token, $facturaId, ['motivo' => 'Flete que no se incluyó'])
            ->assertCreated()
            ->assertJsonPath('tipo_comprobante', '08')
            ->assertJsonPath('numero', 'FD01-1')
            ->assertJsonPath('estado', 'aceptado')
            ->assertJsonPath('comprobante_afectado.numero', 'F001-1')
            ->assertJsonPath('motivo.codigo', '02')
            ->assertJsonPath('motivo.descripcion', 'Flete que no se incluyó')
            ->assertJsonPath('cliente.numero_documento', Facturacion::RUC_CLIENTE)
            ->assertJsonPath('totales.total', '23.60')
            ->assertJsonPath('totales.igv', '3.60');

        $documento = $this->sunat->firmados[1];
        $this->assertSame(TipoComprobante::NotaDebito, $documento->tipo);
        $this->assertSame('F001-1', $documento->afectado?->numero());

        // Reintentar no emite otra; y un débito no consume el saldo por acreditar de la factura.
        $this->notaDebito($token, $facturaId)->assertOk();
        $this->notaCredito($token, $facturaId)->assertCreated()->assertJsonPath('totales.total', '137.35');
    }

    public function test_la_nota_de_debito_exige_items_un_motivo_de_su_catalogo_y_su_serie(): void
    {
        [$empresa, $token] = $this->empresa();
        $facturaId = $this->factura($token);

        $this->notaDebito($token, $facturaId, ['items' => null])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->notaDebito($token, $facturaId, ['motivo_codigo' => '07'])->assertUnprocessable()->assertJsonValidationErrors('motivo_codigo');
        $this->notaDebito($token, $facturaId)
            ->assertUnprocessable()
            ->assertJsonPath('errors.serie.0', 'La empresa no tiene una serie de nota de débito para Factura (debe empezar con F, ej: FD01). Regístrela en el panel → Series.');

        $this->assertSame(0, Comprobante::where('tipo_comprobante', '08')->count());
    }

    // ---------------- Modo prueba ----------------

    public function test_lo_emitido_en_modo_prueba_no_gasta_la_numeracion_de_produccion(): void
    {
        [$empresa, $token] = $this->empresa();
        $empresa->series()->where('serie', 'F001')->update(['correlativo' => 1520]);

        $prueba = $this->factura($token, 'prueba-1');
        $this->factura($token, 'prueba-2');

        $serie = $empresa->series()->where('serie', 'F001')->firstOrFail();
        $this->assertSame(1520, $serie->correlativo);
        $this->assertSame(2, $serie->correlativo_beta);
        $this->assertStringStartsWith('facturacion/20000000001/beta/', Comprobante::firstOrFail()->xml_path);

        $empresa->update(['entorno' => Entorno::Produccion, 'sol_usuario' => 'MODDATOS', 'sol_clave' => 'moddatos']);

        $this->withToken($token)->getJson('/api/v1/yo')->assertJsonPath('series.2.serie', 'F001')->assertJsonPath('series.2.ultimo_correlativo', 1520);

        // La primera factura real sigue al último número real, aunque en prueba ya existía F001-1.
        $real = $this->withToken($token)->getJson("/api/v1/comprobantes/{$this->factura($token, 'venta-real')}");
        $real->assertJsonPath('numero', 'F001-1521')->assertJsonPath('entorno', 'produccion');
        $this->assertStringStartsWith('facturacion/20000000001/'.now()->format('Y'), Comprobante::where('correlativo', 1521)->firstOrFail()->xml_path);

        // Un comprobante de prueba ya no se modifica desde producción.
        $this->notaCredito($token, $prueba)->assertUnprocessable()->assertJsonValidationErrors('comprobante');
    }
}
