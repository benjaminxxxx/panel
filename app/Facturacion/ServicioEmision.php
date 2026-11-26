<?php

namespace App\Facturacion;

use App\Facturacion\Core\Calculo\Decimal;
use App\Facturacion\Core\Dominio\Cliente;
use App\Facturacion\Core\Dominio\Detraccion;
use App\Facturacion\Core\Dominio\DocumentoAfectado;
use App\Facturacion\Core\Dominio\ItemSolicitud;
use App\Facturacion\Core\Dominio\LineaCalculada;
use App\Facturacion\Core\Dominio\SolicitudComprobante;
use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Enums\MotivoNota;
use App\Facturacion\Core\Enums\MotivoNotaCredito;
use App\Facturacion\Core\Enums\MotivoNotaDebito;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;
use App\Facturacion\Core\PreparadorComprobante;
use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Sunat\ResultadoEnvio;
use App\Facturacion\Core\Sunat\ServicioSunat;
use App\Facturacion\Core\Sunat\XmlFirmado;
use App\Facturacion\Core\Validacion\ComprobanteInvalido;
use App\Facturacion\Core\Validacion\ValidadorComprobante;
use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use App\Models\Empresa;
use App\Models\Serie;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Orquesta la emisión: idempotencia → validación/cálculo (core) → numeración → firma → envío → persistencia.
 */
class ServicioEmision
{
    public function __construct(
        private readonly PreparadorComprobante $preparador,
        private readonly ServicioSunat $sunat,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  Payload de la API ya validado estructuralmente.
     *
     * @throws ComprobanteInvalido
     * @throws EmpresaNoHabilitada
     */
    public function emitir(Empresa $empresa, string $sistema, array $datos): ResultadoEmision
    {
        $referencia = (string) $datos['referencia_externa'];

        if ($existente = $this->buscarPorReferencia($empresa, $sistema, $referencia)) {
            return new ResultadoEmision($existente, nuevo: false);
        }

        return $this->procesar($empresa, $sistema, $referencia, $this->solicitudDesdeDatos($datos), informacion: $datos['informacion_adicional'] ?? []);
    }

    /**
     * Emite una nota de crédito sobre una factura o boleta válida de la empresa.
     *
     * Con un motivo total (anulación, devolución total) no se envían ítems: la nota copia el
     * comprobante completo. Con los demás (devolución o descuento por ítem, descuento global,
     * disminución en el valor) se envían los ítems que se acreditan; lo acreditado entre todas
     * las notas nunca supera el total del comprobante.
     *
     * @param  array<string, mixed>  $datos  referencia_externa, motivo_codigo?, motivo?, serie?, fecha_emision?, observacion?, items?
     *
     * @throws ComprobanteInvalido
     * @throws EmpresaNoHabilitada
     */
    public function emitirNotaCredito(Empresa $empresa, string $sistema, Comprobante $afectado, array $datos): ResultadoEmision
    {
        $motivo = MotivoNotaCredito::from((string) ($datos['motivo_codigo'] ?? MotivoNotaCredito::AnulacionOperacion->value));

        if (! $motivo->aplicaA($afectado->tipo_comprobante)) {
            throw new ComprobanteInvalido(['motivo_codigo' => ["SUNAT no admite el motivo «{$motivo->descripcion()}» en notas de crédito de boletas: use «".MotivoNotaCredito::DisminucionValor->descripcion().'» (09).']]);
        }

        return $this->emitirNota(TipoComprobante::NotaCredito, $motivo, $empresa, $sistema, $afectado, $datos);
    }

    /**
     * Emite una nota de débito: aumenta lo cobrado en una factura o boleta válida (intereses,
     * aumento en el valor, penalidades). Siempre lleva sus ítems: el concepto que se suma.
     *
     * @param  array<string, mixed>  $datos  referencia_externa, items, motivo_codigo?, motivo?, serie?, fecha_emision?, observacion?
     *
     * @throws ComprobanteInvalido
     * @throws EmpresaNoHabilitada
     */
    public function emitirNotaDebito(Empresa $empresa, string $sistema, Comprobante $afectado, array $datos): ResultadoEmision
    {
        $motivo = MotivoNotaDebito::from((string) ($datos['motivo_codigo'] ?? MotivoNotaDebito::AumentoValor->value));

        return $this->emitirNota(TipoComprobante::NotaDebito, $motivo, $empresa, $sistema, $afectado, $datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ComprobanteInvalido
     * @throws EmpresaNoHabilitada
     */
    private function emitirNota(TipoComprobante $tipo, MotivoNota $motivo, Empresa $empresa, string $sistema, Comprobante $afectado, array $datos): ResultadoEmision
    {
        $referencia = (string) $datos['referencia_externa'];
        $nota = mb_strtolower($tipo->nombre());

        if ($existente = $this->buscarPorReferencia($empresa, $sistema, $referencia)) {
            return new ResultadoEmision($existente, nuevo: false);
        }

        if (! in_array($afectado->tipo_comprobante, TipoComprobante::ventas(), true)) {
            throw new ComprobanteInvalido(['comprobante' => ['Las notas de crédito y de débito solo se emiten sobre facturas y boletas.']]);
        }

        if (! $afectado->estado->esValido()) {
            throw new ComprobanteInvalido(['comprobante' => ["El comprobante {$afectado->numero()} no está aceptado por SUNAT ({$afectado->estado->value}): no admite una {$nota}."]]);
        }

        if ($afectado->entorno !== $empresa->entorno) {
            throw new ComprobanteInvalido(['comprobante' => ["El comprobante {$afectado->numero()} se emitió en {$this->nombreEntorno($afectado->entorno)} y la empresa ahora trabaja en {$this->nombreEntorno($empresa->entorno)}: no se puede modificar con una {$nota}."]]);
        }

        $items = $datos['items'] ?? null;

        if ($motivo->esTotal() && $items !== null) {
            throw new ComprobanteInvalido(['items' => ["El motivo «{$motivo->descripcion()}» revierte el comprobante completo: no envíe ítems."]]);
        }

        if (! $motivo->esTotal() && $items === null) {
            throw new ComprobanteInvalido(['items' => ["El motivo «{$motivo->descripcion()}» requiere los ítems de la {$nota}."]]);
        }

        $serie = isset($datos['serie'])
            ? strtoupper((string) $datos['serie'])
            : $this->serieDeNota($tipo, $empresa, $afectado);

        $zona = ValidadorComprobante::ZONA_HORARIA;

        $solicitud = new SolicitudComprobante(
            tipo: $tipo,
            serie: $serie,
            fechaEmision: (isset($datos['fecha_emision'])
                ? CarbonImmutable::parse((string) $datos['fecha_emision'], $zona)->setTimezone($zona)
                : CarbonImmutable::now($zona))->toDateTimeImmutable(),
            cliente: new Cliente(
                TipoDocumentoIdentidad::from($afectado->cliente_tipo_documento),
                $afectado->cliente_numero_documento,
                $afectado->cliente_razon_social,
                $afectado->cliente_direccion,
                $afectado->cliente_email,
            ),
            // Total: cada ítem con el importe exacto que se facturó, así la nota suma lo mismo que el comprobante.
            items: $items !== null
                ? $this->itemsDesdeDatos($items)
                : $afectado->items->map(fn (ComprobanteItem $item) => new ItemSolicitud(
                    descripcion: $item->descripcion,
                    cantidad: Decimal::normalizar($item->cantidad),
                    unidad: $item->unidad,
                    codigo: $item->codigo,
                    importe: $item->importe_total,
                    afectacion: $item->tipo_afectacion_igv ?: ItemSolicitud::GRAVADO,
                    porcentajeIgv: $item->tipo_afectacion_igv === ItemSolicitud::GRAVADO ? Decimal::compactar((string) $item->porcentaje_igv) : null,
                ))->all(),
            moneda: $afectado->moneda,
            observacion: $datos['observacion'] ?? null,
            afectado: new DocumentoAfectado(
                $afectado->tipo_comprobante,
                $afectado->serie,
                $afectado->correlativo,
                $motivo,
                trim((string) ($datos['motivo'] ?? '')) ?: $motivo->descripcion(),
            ),
        );

        return $this->procesar($empresa, $sistema, $referencia, $solicitud, $afectado, $datos['informacion_adicional'] ?? []);
    }

    /**
     * Valida, numera, firma, guarda y envía. Común a facturas, boletas y notas de crédito y de débito.
     *
     * @param  list<array{ubicacion: string, etiqueta?: ?string, valor: string}>  $informacion  Datos solo para la representación impresa.
     *
     * @throws ComprobanteInvalido
     * @throws EmpresaNoHabilitada
     */
    private function procesar(Empresa $empresa, string $sistema, string $referencia, SolicitudComprobante $solicitud, ?Comprobante $afectado = null, array $informacion = []): ResultadoEmision
    {
        $credenciales = $empresa->credenciales();
        $calculo = $this->preparador->preparar($solicitud, CarbonImmutable::now(ValidadorComprobante::ZONA_HORARIA)->toDateTimeImmutable());

        try {
            /** @var array{0: Comprobante, 1: XmlFirmado|null} $emitido */
            $emitido = DB::transaction(function () use ($empresa, $sistema, $referencia, $solicitud, $calculo, $credenciales, $afectado, $informacion) {
                if ($afectado && $solicitud->tipo === TipoComprobante::NotaCredito) {
                    // Bloquea el comprobante: dos notas simultáneas no pueden acreditar más que su total.
                    Comprobante::whereKey($afectado->id)->lockForUpdate()->first();
                    $this->verificarSaldoPorAcreditar($afectado, $calculo->totales->importeTotal);
                }

                $serie = Serie::where('empresa_id', $empresa->id)
                    ->where('tipo_comprobante', $solicitud->tipo->value)
                    ->where('serie', $solicitud->serie)
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first();

                if (! $serie) {
                    throw new ComprobanteInvalido(['serie' => ["La serie {$solicitud->serie} no está registrada para {$solicitud->tipo->descripcion()} en esta empresa."]]);
                }

                // Dentro del bloqueo: un reintento concurrente con la misma referencia no consume otro número.
                if ($existente = $this->buscarPorReferencia($empresa, $sistema, $referencia)) {
                    return [$existente, null];
                }

                // Cada entorno numera aparte: el modo prueba no gasta los números de producción.
                $correlativo = $serie->ultimoCorrelativo($credenciales->entorno) + 1;
                $serie->update([Serie::columnaCorrelativo($credenciales->entorno) => $correlativo]);

                // Se firma antes de confirmar: si el certificado falla, el correlativo no se pierde.
                $documento = $this->preparador->documento($empresa->emisor(), $solicitud, $correlativo, $calculo);
                $xml = $this->sunat->firmar($documento, $credenciales);
                $xmlPath = $this->guardarArchivo($empresa, $credenciales->entorno, "{$xml->nombre}.xml", $xml->contenido);

                $totales = $calculo->totales;
                $comprobante = Comprobante::create([
                    'empresa_id' => $empresa->id,
                    'sistema' => $sistema,
                    'referencia_externa' => $referencia,
                    'entorno' => $credenciales->entorno,
                    'tipo_comprobante' => $solicitud->tipo,
                    'serie' => $solicitud->serie,
                    'correlativo' => $correlativo,
                    'comprobante_afectado_id' => $afectado?->id,
                    'motivo_codigo' => $solicitud->afectado?->motivo->value,
                    'motivo_descripcion' => $solicitud->afectado?->descripcionMotivo,
                    // Eloquent guarda la hora "de pared" sin zona: se normaliza a la zona de la app.
                    'fecha_emision' => CarbonImmutable::instance($solicitud->fechaEmision)->setTimezone(config('app.timezone')),
                    'moneda' => $solicitud->moneda,
                    'cliente_tipo_documento' => $solicitud->cliente->tipoDocumento->value,
                    'cliente_numero_documento' => $solicitud->cliente->numeroDocumento,
                    'cliente_razon_social' => $solicitud->cliente->razonSocial,
                    'cliente_direccion' => $solicitud->cliente->direccion,
                    'cliente_email' => $solicitud->cliente->email,
                    'op_gravadas' => $totales->operacionesGravadas,
                    'op_exoneradas' => $totales->operacionesExoneradas,
                    'op_inafectas' => $totales->operacionesInafectas,
                    'igv' => $totales->igv,
                    'total_impuestos' => $totales->totalImpuestos,
                    'valor_venta' => $totales->valorVenta,
                    'sub_total' => $totales->subTotal,
                    'total' => $totales->importeTotal,
                    'monto_letras' => $documento->montoEnLetras,
                    'observacion' => $solicitud->observacion,
                    'informacion_adicional' => $informacion ?: null,
                    'detraccion' => $solicitud->detraccion?->aArreglo($totales->importeTotal),
                    'estado' => EstadoComprobante::Pendiente,
                    'hash' => $xml->hash,
                    'xml_path' => $xmlPath,
                ]);

                $comprobante->items()->createMany(array_map(fn (LineaCalculada $l) => [
                    'orden' => $l->orden,
                    'codigo' => $l->codigo,
                    'descripcion' => $l->descripcion,
                    'unidad' => $l->unidad,
                    'cantidad' => $l->cantidad,
                    'valor_unitario' => $l->valorUnitario,
                    'precio_unitario' => $l->precioUnitario,
                    'tipo_afectacion_igv' => $l->tipoAfectacionIgv,
                    'porcentaje_igv' => $l->porcentajeIgv,
                    'valor_venta' => $l->valorVenta,
                    'base_igv' => $l->baseIgv,
                    'igv' => $l->igv,
                    'total_impuestos' => $l->totalImpuestos,
                    'importe_total' => $l->importeTotal,
                ], $calculo->lineas));

                return [$comprobante, $xml];
            });
        } catch (UniqueConstraintViolationException) {
            // Carrera con la misma referencia en otra serie: la transacción se revirtió, devolvemos el que ganó.
            $existente = $this->buscarPorReferencia($empresa, $sistema, $referencia)
                ?? throw new LogicException('Conflicto de numeración: reintente la solicitud.');

            return new ResultadoEmision($existente, nuevo: false);
        }

        [$comprobante, $xml] = $emitido;

        if ($xml === null) {
            return new ResultadoEmision($comprobante, nuevo: false);
        }

        // Fuera de la transacción: la red nunca retiene el bloqueo de la serie.
        $this->registrarEnvio($comprobante, $this->sunat->enviar($xml, $credenciales));

        return new ResultadoEmision($comprobante, nuevo: true);
    }

    /**
     * Reenvía el MISMO XML firmado de un comprobante pendiente o con error de envío.
     *
     * @throws EmpresaNoHabilitada
     */
    public function reenviar(Comprobante $comprobante): Comprobante
    {
        if (! $comprobante->estado->puedeReenviarse()) {
            throw new LogicException("El comprobante {$comprobante->numero()} ya tiene respuesta definitiva ({$comprobante->estado->value}).");
        }

        $contenido = $comprobante->xml_path ? $this->disco()->get($comprobante->xml_path) : null;
        if (! $contenido) {
            throw new LogicException("No se encontró el XML firmado de {$comprobante->numero()}.");
        }

        $xml = new XmlFirmado($comprobante->nombreArchivo(), $contenido, (string) $comprobante->hash);
        $this->registrarEnvio($comprobante, $this->sunat->enviar($xml, $comprobante->empresa->credenciales()));

        return $comprobante;
    }

    public function disco(): Filesystem
    {
        return Storage::disk(config('facturacion.disco'));
    }

    private function registrarEnvio(Comprobante $comprobante, ResultadoEnvio $resultado): void
    {
        $cdrPath = $resultado->cdrZip
            ? $this->guardarArchivo($comprobante->empresa, $comprobante->entorno, "R-{$comprobante->nombreArchivo()}.zip", $resultado->cdrZip)
            : $comprobante->cdr_path;

        $comprobante->update([
            'estado' => $resultado->estado,
            'sunat_codigo' => $resultado->codigo,
            'sunat_descripcion' => $resultado->descripcion,
            'sunat_notas' => $resultado->notas ?: null,
            'cdr_path' => $cdrPath,
            'intentos_envio' => $comprobante->intentos_envio + 1,
            'enviado_at' => now(),
        ]);
    }

    /**
     * Los archivos de prueba van a su propia carpeta: un número de beta puede repetirse luego en producción.
     */
    private function guardarArchivo(Empresa $empresa, Entorno $entorno, string $nombre, string $contenido): string
    {
        $carpeta = $entorno === Entorno::Beta ? "{$empresa->ruc}/beta" : $empresa->ruc;
        $ruta = config('facturacion.directorio')."/{$carpeta}/".now()->format('Y/m')."/{$nombre}";
        $this->disco()->put($ruta, $contenido);

        return $ruta;
    }

    private function buscarPorReferencia(Empresa $empresa, string $sistema, string $referencia): ?Comprobante
    {
        return Comprobante::where('empresa_id', $empresa->id)
            ->where('sistema', $sistema)
            ->where('referencia_externa', $referencia)
            ->first();
    }

    private function nombreEntorno(Entorno $entorno): string
    {
        return $entorno === Entorno::Beta ? 'modo prueba' : 'producción';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function solicitudDesdeDatos(array $datos): SolicitudComprobante
    {
        $zona = ValidadorComprobante::ZONA_HORARIA;
        $fecha = isset($datos['fecha_emision'])
            ? CarbonImmutable::parse((string) $datos['fecha_emision'], $zona)->setTimezone($zona)
            : CarbonImmutable::now($zona);

        $cliente = isset($datos['cliente'])
            ? new Cliente(
                TipoDocumentoIdentidad::from((string) $datos['cliente']['tipo_documento']),
                trim((string) $datos['cliente']['numero_documento']),
                trim((string) $datos['cliente']['razon_social']),
                $datos['cliente']['direccion'] ?? null,
                $datos['cliente']['email'] ?? null,
            )
            : Cliente::varios();

        return new SolicitudComprobante(
            tipo: TipoComprobante::from((string) $datos['tipo_comprobante']),
            serie: strtoupper((string) $datos['serie']),
            fechaEmision: $fecha->toDateTimeImmutable(),
            cliente: $cliente,
            items: $this->itemsDesdeDatos($datos['items']),
            moneda: strtoupper((string) ($datos['moneda'] ?? 'PEN')),
            observacion: $datos['observacion'] ?? null,
            detraccion: isset($datos['detraccion']['codigo']) ? new Detraccion(
                codigo: (string) $datos['detraccion']['codigo'],
                // Sin porcentaje se usa el del catálogo para ese código.
                porcentaje: $this->numero($datos['detraccion']['porcentaje'] ?? config("facturacion.detracciones.{$datos['detraccion']['codigo']}.porcentaje", '0')),
                cuenta: trim((string) ($datos['detraccion']['cuenta'] ?? '')),
            ) : null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<ItemSolicitud>
     */
    private function itemsDesdeDatos(array $items): array
    {
        return array_map(fn (array $item) => new ItemSolicitud(
            descripcion: trim((string) $item['descripcion']),
            cantidad: $this->numero($item['cantidad']),
            unidad: strtoupper((string) ($item['unidad'] ?? 'NIU')),
            codigo: isset($item['codigo']) ? (string) $item['codigo'] : null,
            precioUnitario: isset($item['precio_unitario']) ? $this->numero($item['precio_unitario']) : null,
            valorUnitario: isset($item['valor_unitario']) ? $this->numero($item['valor_unitario']) : null,
            importe: isset($item['importe']) ? $this->numero($item['importe']) : null,
            afectacion: (string) ($item['afectacion_igv'] ?? ItemSolicitud::GRAVADO),
            porcentajeIgv: isset($item['porcentaje_igv']) ? $this->numero($item['porcentaje_igv']) : null,
        ), array_values($items));
    }

    /**
     * Serie de notas (de crédito o de débito) de la empresa para ese comprobante: la activa que empieza con su misma letra.
     *
     * @throws ComprobanteInvalido
     */
    private function serieDeNota(TipoComprobante $tipo, Empresa $empresa, Comprobante $afectado): string
    {
        $prefijo = $afectado->tipo_comprobante->prefijoSerie();

        return Serie::where('empresa_id', $empresa->id)
            ->where('tipo_comprobante', $tipo->value)
            ->where('serie', 'like', "{$prefijo}%")
            ->where('activo', true)
            ->orderBy('serie')
            ->value('serie')
            ?? throw new ComprobanteInvalido(['serie' => ['La empresa no tiene una serie de '.mb_strtolower($tipo->nombre())." para {$afectado->tipo_comprobante->nombre()} (debe empezar con {$prefijo}, ej: {$tipo->serieEjemplo($prefijo)}). Regístrela en el panel → Series."]]);
    }

    /**
     * Lo acreditado por las notas vigentes (todas menos las rechazadas) más esta no puede superar el total del comprobante.
     *
     * @throws ComprobanteInvalido
     */
    private function verificarSaldoPorAcreditar(Comprobante $afectado, string $importe): void
    {
        $acreditado = Decimal::normalizar((string) $afectado->notasCredito()
            ->where('estado', '!=', EstadoComprobante::Rechazado->value)
            ->sum('total'));

        $saldo = Decimal::redondear(Decimal::restar($afectado->total, $acreditado));

        if (Decimal::comparar($importe, $saldo) > 0) {
            throw new ComprobanteInvalido(['comprobante' => [
                Decimal::esPositivo($acreditado)
                    ? "El comprobante {$afectado->numero()} ya tiene notas de crédito por S/ {$acreditado}: solo queda S/ {$saldo} por acreditar."
                    : "La nota de crédito (S/ {$importe}) no puede superar el total del comprobante {$afectado->numero()} (S/ {$afectado->total}).",
            ]]);
        }
    }

    private function numero(mixed $valor): string
    {
        return is_float($valor) ? Decimal::normalizar($valor) : trim((string) $valor);
    }
}
