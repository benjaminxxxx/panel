<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El modo prueba numera aparte: lo emitido en beta ya no consume el correlativo real.
     * Lo ya emitido en beta se descuenta del correlativo de producción de cada serie.
     */
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->unsignedInteger('correlativo_beta')->default(0)->after('correlativo');
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->unique(['empresa_id', 'entorno', 'tipo_comprobante', 'serie', 'correlativo'], 'comprobantes_numero_entorno_unique');
            $table->dropUnique('comprobantes_numero_unique');
        });

        foreach (DB::table('series')->get() as $serie) {
            $emitidos = fn (string $entorno) => DB::table('comprobantes')
                ->where('empresa_id', $serie->empresa_id)
                ->where('tipo_comprobante', $serie->tipo_comprobante)
                ->where('serie', $serie->serie)
                ->where('entorno', $entorno);

            $enBeta = $emitidos('beta')->count();

            if ($enBeta === 0) {
                continue;
            }

            DB::table('series')->where('id', $serie->id)->update([
                'correlativo_beta' => (int) $emitidos('beta')->max('correlativo'),
                'correlativo' => max($serie->correlativo - $enBeta, (int) $emitidos('produccion')->max('correlativo')),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->unique(['empresa_id', 'tipo_comprobante', 'serie', 'correlativo'], 'comprobantes_numero_unique');
            $table->dropUnique('comprobantes_numero_entorno_unique');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('correlativo_beta');
        });
    }
};
