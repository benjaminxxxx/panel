<?php

namespace App\Console\Commands;

use App\Licencias\PublicadorVersiones;
use App\Models\Version;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Publica una versión desde la línea de comandos (lo usa scripts/compilar.ps1 -Publicar). */
#[Signature('versiones:publicar {version} {archivo} {--notas=} {--borrador : Subirla sin publicar}')]
#[Description('Sube, firma y publica una versión del programa del local')]
class PublicarVersion extends Command
{
    public function handle(PublicadorVersiones $publicador): int
    {
        $version = $this->argument('version');
        $archivo = $this->argument('archivo');
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->error('Versión inválida: usa el formato 1.0.1');

            return self::FAILURE;
        }
        if (! is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");

            return self::FAILURE;
        }
        if (Version::where('version', $version)->exists()) {
            $this->error("La versión {$version} ya existe");

            return self::FAILURE;
        }
        $v = $publicador->publicar($version, $archivo, $this->option('notas'), ! $this->option('borrador'));
        $this->info("Versión {$v->version} ".($v->publicada ? 'publicada' : 'subida como borrador')." · sha256 {$v->sha256}");

        return self::SUCCESS;
    }
}
