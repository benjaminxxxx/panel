<?php

namespace App\Licencias;

use App\Models\Version;
use Illuminate\Support\Facades\Storage;

/** Guarda un instalador o APK nuevo, calcula su huella y la firma. Lo usan el panel y artisan. */
final class PublicadorVersiones
{
    public function __construct(private Firmador $firmador) {}

    public function publicar(string $version, string $rutaArchivo, ?string $notas, bool $publicada, string $plataforma = 'windows', string $aplicacion = 'restaurante'): Version
    {
        $v = new Version(['aplicacion' => $aplicacion, 'plataforma' => $plataforma, 'version' => $version]);
        $sha256 = hash_file('sha256', $rutaArchivo);
        $destino = 'versiones/'.$v->nombreArchivo();
        $origen = fopen($rutaArchivo, 'rb');
        Storage::disk('local')->put($destino, $origen);
        if (is_resource($origen)) {
            fclose($origen);
        }

        $v->fill([
            'notas' => $notas,
            'archivo' => $destino,
            'sha256' => $sha256,
            // La firma incluye el sistema: un instalador del restaurante no pasa por actualización de la panadería.
            // Android verifica por su cuenta que la actualización venga firmada con la misma llave.
            'firma' => $this->firmador->firmar(Firmador::mensajeVersion($version, $sha256, $aplicacion)),
            'tamano' => filesize($rutaArchivo),
            'publicada' => $publicada,
        ])->save();

        return $v;
    }
}
