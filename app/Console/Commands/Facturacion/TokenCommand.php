<?php

namespace App\Console\Commands\Facturacion;

use App\Models\Empresa;
use App\Models\EmpresaToken;
use Illuminate\Console\Command;

class TokenCommand extends Command
{
    protected $signature = 'facturacion:token
        {ruc : RUC de la empresa}
        {sistema : pos | alquiler | restaurant | hotel}
        {--nombre= : Etiqueta para identificar el token (ej: "POS tienda centro")}';

    protected $description = 'Genera un token de API que vincula un sistema con una empresa';

    public function handle(): int
    {
        $empresa = Empresa::where('ruc', $this->argument('ruc'))->first();
        if (! $empresa) {
            $this->error('Empresa no encontrada.');

            return self::FAILURE;
        }

        $sistema = (string) $this->argument('sistema');
        $sistemas = array_keys(config('facturacion.sistemas'));
        if (! in_array($sistema, $sistemas, true)) {
            $this->error('Sistema inválido. Use: '.implode(', ', $sistemas));

            return self::FAILURE;
        }

        [, $plano] = EmpresaToken::generar($empresa, $sistema, (string) ($this->option('nombre') ?: $sistema));

        $this->info("Token para {$empresa->ruc} / {$sistema} (guárdelo ahora, no se vuelve a mostrar):");
        $this->line($plano);

        return self::SUCCESS;
    }
}
