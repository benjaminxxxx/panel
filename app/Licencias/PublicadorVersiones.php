<?php

namespace App\Licencias;

use App\Models\Version;
use Illuminate\Support\Facades\Storage;

/** Guarda un ejecutable nuevo, calcula su huella y la firma. Lo usan el panel y artisan. */
final class PublicadorVersiones
{
    public function __construct(private Firmador $firmador) {}

    public function publicar(string $version, string $rutaArchivo, ?string $notas, bool $publicada): Version
    {
        $sha256 = hash_file('sha256', $rutaArchivo);
        $destino = "versiones/Restaurante-Setup-{$version}.exe";
        $origen = fopen($rutaArchivo, 'rb');
        Storage::disk('local')->put($destino, $origen);
        if (is_resource($origen)) {
            fclose($origen);
        }

        return Version::create([
            'version' => $version,
            'notas' => $notas,
            'archivo' => $destino,
            'sha256' => $sha256,
            'firma' => $this->firmador->firmar(Firmador::mensajeVersion($version, $sha256)),
            'tamano' => filesize($rutaArchivo),
            'publicada' => $publicada,
        ]);
    }
}
