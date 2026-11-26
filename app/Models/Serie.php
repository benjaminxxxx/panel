<?php

namespace App\Models;

use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Sunat\Entorno;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $empresa_id
 * @property TipoComprobante $tipo_comprobante
 * @property string $serie
 * @property int $correlativo Último número usado en producción.
 * @property int $correlativo_beta Último número usado en modo prueba (no gasta la numeración real).
 * @property bool $activo
 */
#[Fillable(['empresa_id', 'tipo_comprobante', 'serie', 'correlativo', 'correlativo_beta', 'activo'])]
class Serie extends Model
{
    protected function casts(): array
    {
        return [
            'tipo_comprobante' => TipoComprobante::class,
            'correlativo' => 'integer',
            'correlativo_beta' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Cada entorno numera por su cuenta: lo emitido en modo prueba no consume los números
     * que la empresa usará (o ya usa) en producción.
     */
    public static function columnaCorrelativo(Entorno $entorno): string
    {
        return $entorno === Entorno::Beta ? 'correlativo_beta' : 'correlativo';
    }

    public function ultimoCorrelativo(Entorno $entorno): int
    {
        return (int) $this->{self::columnaCorrelativo($entorno)};
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
