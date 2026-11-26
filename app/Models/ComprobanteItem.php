<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $comprobante_id
 * @property int $orden
 * @property string|null $codigo
 * @property string $descripcion
 * @property string $unidad
 * @property string $cantidad
 * @property string $valor_unitario
 * @property string $precio_unitario
 * @property string $tipo_afectacion_igv
 * @property string $porcentaje_igv
 * @property string $valor_venta
 * @property string $base_igv
 * @property string $igv
 * @property string $total_impuestos
 * @property string $importe_total
 */
#[Fillable([
    'comprobante_id', 'orden', 'codigo', 'descripcion', 'unidad', 'cantidad', 'valor_unitario', 'precio_unitario',
    'tipo_afectacion_igv', 'porcentaje_igv', 'valor_venta', 'base_igv', 'igv', 'total_impuestos', 'importe_total',
])]
class ComprobanteItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:6',
            'valor_unitario' => 'decimal:10',
            'precio_unitario' => 'decimal:10',
            'porcentaje_igv' => 'decimal:2',
            'valor_venta' => 'decimal:2',
            'base_igv' => 'decimal:2',
            'igv' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'importe_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Comprobante, $this> */
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
