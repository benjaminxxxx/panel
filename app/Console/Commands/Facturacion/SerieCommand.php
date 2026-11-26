<?php

namespace App\Console\Commands\Facturacion;

use App\Facturacion\Core\Enums\TipoComprobante;
use App\Models\Empresa;
use App\Models\Serie;
use Illuminate\Console\Command;

class SerieCommand extends Command
{
    protected $signature = 'facturacion:serie
        {ruc : RUC de la empresa}
        {tipo : factura | boleta | nota_credito | nota_debito | 01 | 03 | 07 | 08}
        {serie : Ej: F001, B001, FC01 y BC01 (notas de crédito), FD01 y BD01 (notas de débito)}
        {--correlativo=0 : Último número ya emitido (si migra de otro sistema, ponga el último usado)}';

    protected $description = 'Registra una serie de comprobantes para una empresa';

    public function handle(): int
    {
        $empresa = Empresa::where('ruc', $this->argument('ruc'))->first();
        if (! $empresa) {
            $this->error('Empresa no encontrada.');

            return self::FAILURE;
        }

        $tipo = match (strtolower((string) $this->argument('tipo'))) {
            'factura', '01' => TipoComprobante::Factura,
            'boleta', '03' => TipoComprobante::Boleta,
            'nota_credito', '07' => TipoComprobante::NotaCredito,
            'nota_debito', '08' => TipoComprobante::NotaDebito,
            default => null,
        };
        if (! $tipo) {
            $this->error('Tipo inválido: use factura, boleta, nota_credito o nota_debito.');

            return self::FAILURE;
        }

        $serie = strtoupper((string) $this->argument('serie'));
        if (! preg_match($tipo->patronSerie(), $serie)) {
            $this->error('La serie debe tener 4 caracteres y empezar con '.implode(' o ', $tipo->prefijosSerie()).'.');

            return self::FAILURE;
        }

        $registro = Serie::firstOrNew(['empresa_id' => $empresa->id, 'tipo_comprobante' => $tipo, 'serie' => $serie]);

        if ($registro->exists && $registro->correlativo > (int) $this->option('correlativo')) {
            $this->error("La serie ya existe con correlativo {$registro->correlativo}; no se puede retroceder.");

            return self::FAILURE;
        }

        $registro->fill(['correlativo' => (int) $this->option('correlativo'), 'activo' => true])->save();

        $this->info("Serie {$serie} ({$tipo->descripcion()}) lista. Próximo número: ".($registro->correlativo + 1));

        return self::SUCCESS;
    }
}
