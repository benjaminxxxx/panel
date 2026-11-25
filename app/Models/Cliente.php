<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property string|null $contacto
 * @property string $clave_hash
 * @property string $clave_inicio
 * @property string $estado
 * @property string|null $motivo_bloqueo
 * @property bool $bloqueo_automatico
 * @property Carbon|null $vence_el
 * @property Carbon|null $ultimo_contacto_at
 * @property string|null $version_reportada
 * @property string|null $ip_reportada
 */
#[Fillable(['nombre', 'codigo', 'contacto', 'estado', 'motivo_bloqueo', 'bloqueo_automatico', 'vence_el'])]
#[Hidden(['clave_hash'])]
class Cliente extends Model
{
    /** Mismos valores por defecto que la tabla, para que un cliente nuevo los tenga en memoria. */
    protected $attributes = ['estado' => 'activo', 'bloqueo_automatico' => true];

    protected function casts(): array
    {
        return [
            'bloqueo_automatico' => 'boolean',
            'vence_el' => 'date',
            'ultimo_contacto_at' => 'datetime',
        ];
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->latest('pagado_el');
    }

    /** Genera una clave nueva, guarda solo su hash y la devuelve (se muestra una sola vez). */
    public function nuevaClave(?string $clave = null): string
    {
        $clave ??= 'rst_'.Str::random(40);
        $this->clave_hash = hash('sha256', $clave);
        $this->clave_inicio = substr($clave, 0, 8);

        return $clave;
    }

    public static function porClave(string $clave): ?self
    {
        return static::where('clave_hash', hash('sha256', $clave))->first();
    }
}
