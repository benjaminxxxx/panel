<?php

namespace App\Models;

use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Sunat\Entorno;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $empresa_id
 * @property string $sistema
 * @property string $referencia_externa
 * @property Entorno $entorno
 * @property TipoComprobante $tipo_comprobante
 * @property string $serie
 * @property int $correlativo
 * @property Carbon $fecha_emision
 * @property string $moneda
 * @property string $cliente_tipo_documento
 * @property string $cliente_numero_documento
 * @property string $cliente_razon_social
 * @property string|null $cliente_direccion
 * @property string|null $cliente_email
 * @property string $op_gravadas
 * @property string $op_exoneradas
 * @property string $op_inafectas
 * @property string $igv
 * @property string $total_impuestos
 * @property string $valor_venta
 * @property string $sub_total
 * @property string $total
 * @property string $monto_letras
 * @property string|null $observacion
 * @property EstadoComprobante $estado
 * @property string|null $sunat_codigo
 * @property string|null $sunat_descripcion
 * @property list<string>|null $sunat_notas
 * @property string|null $hash
 * @property string|null $xml_path
 * @property string|null $cdr_path
 * @property int $intentos_envio
 * @property Carbon|null $enviado_at
 * @property int|null $comprobante_afectado_id
 * @property string|null $motivo_codigo
 * @property string|null $motivo_descripcion
 * @property-read Empresa $empresa
 * @property-read Comprobante|null $afectado
 */
#[Fillable([
    'empresa_id', 'sistema', 'referencia_externa', 'entorno',
    'tipo_comprobante', 'serie', 'correlativo', 'fecha_emision', 'moneda',
    'comprobante_afectado_id', 'motivo_codigo', 'motivo_descripcion',
    'cliente_tipo_documento', 'cliente_numero_documento', 'cliente_razon_social', 'cliente_direccion', 'cliente_email',
    'op_gravadas', 'op_exoneradas', 'op_inafectas', 'igv', 'total_impuestos', 'valor_venta', 'sub_total', 'total',
    'monto_letras', 'observacion', 'informacion_adicional', 'detraccion',
    'estado', 'sunat_codigo', 'sunat_descripcion', 'sunat_notas', 'hash', 'xml_path', 'cdr_path', 'intentos_envio', 'enviado_at',
])]
class Comprobante extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Comprobante $comprobante) {
            $comprobante->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'entorno' => Entorno::class,
            'tipo_comprobante' => TipoComprobante::class,
            'estado' => EstadoComprobante::class,
            'correlativo' => 'integer',
            'fecha_emision' => 'datetime',
            'op_gravadas' => 'decimal:2',
            'op_exoneradas' => 'decimal:2',
            'op_inafectas' => 'decimal:2',
            'igv' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'valor_venta' => 'decimal:2',
            'sub_total' => 'decimal:2',
            'total' => 'decimal:2',
            'sunat_notas' => 'array',
            'informacion_adicional' => 'array',
            'detraccion' => 'array',
            'intentos_envio' => 'integer',
            'enviado_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<ComprobanteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteItem::class)->orderBy('orden');
    }

    /**
     * La factura o boleta que esta nota (de crédito o de débito) modifica.
     *
     * @return BelongsTo<Comprobante, $this>
     */
    public function afectado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'comprobante_afectado_id');
    }

    /**
     * Notas de crédito y de débito emitidas sobre este comprobante.
     *
     * @return HasMany<Comprobante, $this>
     */
    public function notas(): HasMany
    {
        return $this->hasMany(self::class, 'comprobante_afectado_id')->orderBy('id');
    }

    /**
     * Notas de crédito emitidas sobre este comprobante.
     *
     * @return HasMany<Comprobante, $this>
     */
    public function notasCredito(): HasMany
    {
        return $this->notas()->where('tipo_comprobante', TipoComprobante::NotaCredito->value);
    }

    /**
     * Datos adicionales a imprimir en una zona del comprobante.
     *
     * @param  'cabecera'|'centro'|'pie'  $ubicacion
     * @return list<array{ubicacion: string, etiqueta: ?string, valor: string}>
     */
    public function informacionEn(string $ubicacion): array
    {
        return array_values(array_filter($this->informacion_adicional ?? [], fn (array $dato) => ($dato['ubicacion'] ?? null) === $ubicacion));
    }

    public function esNotaCredito(): bool
    {
        return $this->tipo_comprobante === TipoComprobante::NotaCredito;
    }

    /**
     * Nota de crédito o de débito.
     */
    public function esNota(): bool
    {
        return $this->tipo_comprobante->esNota();
    }

    public function numero(): string
    {
        return "{$this->serie}-{$this->correlativo}";
    }

    /**
     * Nombre de archivo SUNAT: RUC-TIPO-SERIE-CORRELATIVO.
     */
    public function nombreArchivo(): string
    {
        return "{$this->empresa->ruc}-{$this->tipo_comprobante->value}-{$this->numero()}";
    }
}
