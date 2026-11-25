<?php

namespace App\Licencias;

use App\Models\Version;
use Illuminate\Support\Facades\Storage;

/** Guarda un instalador o APK nuevo, calcula su huella y la firma. Lo usan el panel y artisan. */
final class PublicadorVersiones
{
    public function __construct(private Firmador $firmador) {}

    public function publicar(string $version, string $rutaArchivo, ?string $notas, bool $publicada, string $plataforma = 'windows'): Version
    {
        $sha256 = hash_file('sha256', $rutaArchivo);
        $destino = $plataforma === 'android'
            ? "versiones/Restaurante-{$version}.apk"
            : "versiones/Restaurante-Setup-{$version}.exe";
        $origen = fopen($rutaArchivo, 'rb');
        Storage::disk('local')->put($destino, $origen);
        if (is_resource($origen)) {
            fclose($origen);
        }

        return Version::create([
            'plataforma' => $plataforma,
            'version' => $version,
            'notas' => $notas,
            'archivo' => $destino,
            'sha256' => $sha256,
            // Android verifica por su cuenta que la actualización venga firmada con la misma llave.
            'firma' => $this->firmador->firmar(Firmador::mensajeVersion($version, $sha256)),
            'tamano' => filesize($rutaArchivo),
            'publicada' => $publicada,
        ]);
    }
}
