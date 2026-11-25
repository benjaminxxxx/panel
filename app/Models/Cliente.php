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
#[Hidden(['clave_hash', 'activacion_hash'])]
class Cliente extends Model
{
    /** Mismos valores por defecto que la tabla, para que un cliente nuevo los tenga en memoria. */
    protected $attributes = ['estado' => 'activo', 'bloqueo_automatico' => true];

    /** Letras y números sin los que se confunden (0/O, 1/I/L). */
    private const ALFABETO_ACTIVACION = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const MINUTOS_ACTIVACION = 120;

    protected function casts(): array
    {
        return [
            'bloqueo_automatico' => 'boolean',
            'vence_el' => 'date',
            'ultimo_contacto_at' => 'datetime',
            'activacion_expira_at' => 'datetime',
        ];
    }

    /** Genera un código de activación de un solo uso (ej. K7PM-2XQA) válido por 2 horas. */
    public function nuevoCodigoActivacion(): string
    {
        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= self::ALFABETO_ACTIVACION[random_int(0, strlen(self::ALFABETO_ACTIVACION) - 1)];
        }
        $this->forceFill([
            'activacion_hash' => hash('sha256', $codigo),
            'activacion_expira_at' => now()->addMinutes(self::MINUTOS_ACTIVACION),
        ])->save();

        return substr($codigo, 0, 4).'-'.substr($codigo, 4);
    }

    /** Busca el cliente de un código vigente. Acepta minúsculas y con o sin guion. */
    public static function porCodigoActivacion(string $codigo): ?self
    {
        $normal = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));

        return static::where('activacion_hash', hash('sha256', $normal))
            ->where('activacion_expira_at', '>', now())
            ->first();
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
