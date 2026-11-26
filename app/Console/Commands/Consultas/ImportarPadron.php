<?php

namespace App\Console\Commands\Consultas;

use App\Consultas\PadronRuc;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Descarga (o lee de un archivo) el padrón reducido del RUC de SUNAT y lo carga en la base. */
#[Signature('consultas:padron {archivo? : .zip o .txt ya descargado (si no, se descarga de SUNAT)}')]
#[Description('Importa el padrón reducido del RUC publicado por SUNAT para consultar RUC sin depender de su web')]
class ImportarPadron extends Command
{
    public function handle(PadronRuc $padron): int
    {
        $archivo = $this->argument('archivo');
        if (! $archivo) {
            $archivo = storage_path('app/padron_reducido_ruc.zip');
            $this->info('Descargando '.PadronRuc::URL.' …');
            try {
                Http::timeout(1800)->sink($archivo)->get(PadronRuc::URL)->throw();
            } catch (Throwable $e) {
                $this->error('No se pudo descargar el padrón: '.$e->getMessage());

                return self::FAILURE;
            }
        }
        if (! is_file($archivo)) {
            $this->error("No existe {$archivo}");

            return self::FAILURE;
        }
        $total = $padron->importar($archivo, function (int $n) {
            if ($n % 200000 === 0) {
                $this->line(number_format($n).' contribuyentes…');
            }
        });
        $this->info('Padrón actualizado: '.number_format($total).' contribuyentes.');

        return self::SUCCESS;
    }
}
