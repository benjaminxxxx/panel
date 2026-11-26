<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Una versión publicada de un sistema (aplicacion: restaurante, panaderia) para una
 * plataforma: windows (instalador del servidor del local) o android (app de tablets).
 * Cada sistema y plataforma lleva su propia numeración.
 */
#[Table('versiones')]
#[Fillable(['aplicacion', 'plataforma', 'version', 'notas', 'archivo', 'sha256', 'firma', 'tamano', 'publicada'])]
class Version extends Model
{
    public const PLATAFORMAS = ['windows', 'android'];

    protected $attributes = ['aplicacion' => 'restaurante', 'plataforma' => 'windows'];

    protected function casts(): array
    {
        return ['publicada' => 'boolean', 'tamano' => 'integer'];
    }

    /** La versión publicada más nueva del sistema y plataforma (compara 1.0.10 > 1.0.9). */
    public static function ultimaPublicada(string $plataforma = 'windows', string $aplicacion = 'restaurante'): ?self
    {
        return static::where('publicada', true)->where('plataforma', $plataforma)->where('aplicacion', $aplicacion)->get()
            ->sort(fn (self $a, self $b) => version_compare($b->version, $a->version))
            ->first();
    }

    /** Nombre del archivo que descarga el proveedor o el local (ej. Panaderia-Setup-1.0.0.exe). */
    public function nombreArchivo(): string
    {
        $base = ucfirst($this->aplicacion);

        return $this->plataforma === 'android' ? "{$base}-{$this->version}.apk" : "{$base}-Setup-{$this->version}.exe";
    }

    /** Número entero que Android compara: 1.2.3 → 10203 (igual que android/app/build.gradle.kts). */
    public function codigoAndroid(): int
    {
        [$a, $b, $c] = array_map('intval', explode('.', $this->version) + [0, 0, 0]);

        return $a * 10000 + $b * 100 + $c;
    }

    /** @return array<string, string> */
    public static function aplicaciones(): array
    {
        return config('licencias.aplicaciones');
    }
}
