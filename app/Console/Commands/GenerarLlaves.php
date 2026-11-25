<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Crea el par de llaves Ed25519 de las licencias. La privada se guarda en .env y nunca
 * sale del panel; la pública se copia al programa del local (internal/licencia/llave.go).
 * Cambiarlas invalida las licencias y versiones firmadas antes: hazlo una sola vez.
 */
#[Signature('licencias:llaves {--forzar : Reemplazar la llave existente}')]
#[Description('Genera las llaves para firmar licencias y versiones')]
class GenerarLlaves extends Command
{
    public function handle(): int
    {
        $env = base_path('.env');
        $contenido = file_get_contents($env);
        $existente = config('licencias.llave_privada');

        if ($existente && ! $this->option('forzar')) {
            $privada = base64_decode($existente);
            $this->info('Ya hay una llave privada. Su llave pública es:');
        } else {
            $par = sodium_crypto_sign_keypair();
            $privada = sodium_crypto_sign_secretkey($par);
            $linea = 'LICENCIA_LLAVE_PRIVADA='.base64_encode($privada);
            $contenido = preg_match('/^LICENCIA_LLAVE_PRIVADA=.*$/m', $contenido)
                ? preg_replace('/^LICENCIA_LLAVE_PRIVADA=.*$/m', $linea, $contenido)
                : rtrim($contenido)."\n\n".$linea."\n";
            file_put_contents($env, $contenido);
            $this->info('Llave privada guardada en .env. Llave pública (cópiala al programa del local):');
        }
        $this->line(base64_encode(sodium_crypto_sign_publickey_from_secretkey($privada)));

        return self::SUCCESS;
    }
}
