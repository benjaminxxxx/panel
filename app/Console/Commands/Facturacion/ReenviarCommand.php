<?php

namespace App\Console\Commands\Facturacion;

use App\Facturacion\Core\Enums\EstadoComprobante;
use App\Facturacion\ServicioEmision;
use App\Models\Comprobante;
use Illuminate\Console\Command;
use Throwable;

class ReenviarCommand extends Command
{
    protected $signature = 'facturacion:reenviar
        {--minutos=5 : Solo comprobantes con más de N minutos sin respuesta}
        {--limite=50}';

    protected $description = 'Reenvía a SUNAT los comprobantes pendientes o con error de envío';

    public function handle(ServicioEmision $emision): int
    {
        $pendientes = Comprobante::with('empresa')
            ->whereIn('estado', [EstadoComprobante::Pendiente, EstadoComprobante::ErrorEnvio])
            ->where('updated_at', '<=', now()->subMinutes((int) $this->option('minutos')))
            ->orderBy('id')
            ->limit((int) $this->option('limite'))
            ->get();

        foreach ($pendientes as $comprobante) {
            try {
                $emision->reenviar($comprobante);
                $this->line("{$comprobante->empresa->ruc} {$comprobante->numero()} → {$comprobante->estado->value} {$comprobante->sunat_descripcion}");
            } catch (Throwable $e) {
                $this->error("{$comprobante->empresa->ruc} {$comprobante->numero()}: {$e->getMessage()}");
            }
        }

        $this->info("Procesados: {$pendientes->count()}");

        return self::SUCCESS;
    }
}
