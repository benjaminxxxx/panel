<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Una versión publicada. plataforma: windows (instalador del servidor del local) o
 * android (app de tablets y celulares). Cada plataforma lleva su propia numeración.
 */
#[Table('versiones')]
#[Fillable(['plataforma', 'version', 'notas', 'archivo', 'sha256', 'firma', 'tamano', 'publicada'])]
class Version extends Model
{
    public const PLATAFORMAS = ['windows', 'android'];

    protected $attributes = ['plataforma' => 'windows'];

    protected function casts(): array
    {
        return ['publicada' => 'boolean', 'tamano' => 'integer'];
    }

    /** La versión publicada más nueva de la plataforma (compara 1.0.10 > 1.0.9). */
    public static function ultimaPublicada(string $plataforma = 'windows'): ?self
    {
        return static::where('publicada', true)->where('plataforma', $plataforma)->get()
            ->sort(fn (self $a, self $b) => version_compare($b->version, $a->version))
            ->first();
    }

    /** Número entero que Android compara: 1.2.3 → 10203 (igual que android/app/build.gradle.kts). */
    public function codigoAndroid(): int
    {
        [$a, $b, $c] = array_map('intval', explode('.', $this->version) + [0, 0, 0]);

        return $a * 10000 + $b * 100 + $c;
    }
}
