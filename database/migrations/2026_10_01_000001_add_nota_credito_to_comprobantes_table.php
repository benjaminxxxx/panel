<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notas de crédito: son comprobantes (tipo 07) que apuntan a la factura o boleta que modifican.
     */
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->foreignId('comprobante_afectado_id')->nullable()->after('correlativo')->constrained('comprobantes')->restrictOnDelete();
            $table->string('motivo_codigo', 2)->nullable()->after('comprobante_afectado_id'); // Catálogo 09
            $table->string('motivo_descripcion', 250)->nullable()->after('motivo_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comprobante_afectado_id');
            $table->dropColumn(['motivo_codigo', 'motivo_descripcion']);
        });
    }
};
