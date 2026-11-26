<?php

namespace App\Console\Commands;

use App\Licencias\PublicadorVersiones;
use App\Models\Version;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Publica una versión desde la línea de comandos (lo usa scripts/compilar.ps1 -Publicar). */
#[Signature('versiones:publicar {version} {archivo} {--notas=} {--borrador : Subirla sin publicar} {--android : Es la app Android (.apk)} {--app=restaurante : Sistema: restaurante o panaderia}')]
#[Description('Sube, firma y publica una versión del instalador de Windows o de la app Android')]
class PublicarVersion extends Command
{
    public function handle(PublicadorVersiones $publicador): int
    {
        $plataforma = $this->option('android') ? 'android' : 'windows';
        $aplicacion = (string) $this->option('app');
        if (! array_key_exists($aplicacion, Version::aplicaciones())) {
            $this->error('Sistema desconocido: '.$aplicacion.' (usa '.implode(' o ', array_keys(Version::aplicaciones())).')');

            return self::FAILURE;
        }
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
        if (Version::where('aplicacion', $aplicacion)->where('plataforma', $plataforma)->where('version', $version)->exists()) {
            $this->error("La versión {$version} de {$aplicacion} ({$plataforma}) ya existe");

            return self::FAILURE;
        }
        $v = $publicador->publicar($version, $archivo, $this->option('notas'), ! $this->option('borrador'), $plataforma, $aplicacion);
        $this->info("{$aplicacion} {$v->version} ({$plataforma}) ".($v->publicada ? 'publicada' : 'subida como borrador')." · sha256 {$v->sha256}");

        return self::SUCCESS;
    }
}
