<?php

namespace App\Console\Commands;

use App\Licencias\PublicadorVersiones;
use App\Models\Version;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Publica los instaladores que llegaron con el repositorio (git pull).
 *
 * Cada sistema de escritorio, al compilarse con -Publicar, deja en distribucion/<sistema>/ su
 * instalador y un version.json. Al subir el panel a git y desplegarlo, este comando (programado
 * cada 5 minutos y en el despliegue) registra la versión nueva, la firma con la llave del panel y
 * la publica: los locales la instalan solos al abrir el sistema.
 */
#[Signature('versiones:sincronizar')]
#[Description('Registra, firma y publica los instaladores de distribucion/ que aún no están publicados')]
class SincronizarVersiones extends Command
{
    public function handle(PublicadorVersiones $publicador): int
    {
        $nuevas = 0;
        foreach (glob(base_path('distribucion/*/version.json')) ?: [] as $manifiesto) {
            // PowerShell 5 escribe UTF-8 con BOM: se quita antes de leer el JSON.
            $datos = json_decode((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($manifiesto)), true);
            $carpeta = dirname($manifiesto);
            $aplicacion = basename($carpeta);
            if (! is_array($datos) || ! array_key_exists($aplicacion, Version::aplicaciones())) {
                $this->warn("Ignorado {$manifiesto}: sistema desconocido o JSON inválido");

                continue;
            }
            $version = (string) ($datos['version'] ?? '');
            $plataforma = (string) ($datos['plataforma'] ?? 'windows');
            $archivo = $carpeta.DIRECTORY_SEPARATOR.(string) ($datos['archivo'] ?? '');
            if (! preg_match('/^\d+\.\d+\.\d+$/', $version) || ! is_file($archivo)) {
                $this->warn("Ignorado {$manifiesto}: falta la versión o el archivo");

                continue;
            }
            if (Version::where('aplicacion', $aplicacion)->where('plataforma', $plataforma)->where('version', $version)->exists()) {
                continue;
            }
            if (isset($datos['sha256']) && ! hash_equals(strtolower((string) $datos['sha256']), (string) hash_file('sha256', $archivo))) {
                $this->error("{$aplicacion} {$version}: el instalador no coincide con su huella (¿subida incompleta?)");

                continue;
            }
            $publicador->publicar($version, $archivo, $datos['notas'] ?? null, true, $plataforma, $aplicacion);
            $this->info("{$aplicacion} {$version} ({$plataforma}) publicada");
            $nuevas++;
        }
        if ($nuevas === 0) {
            $this->line('Nada nuevo en distribucion/.');
        }

        return self::SUCCESS;
    }
}
