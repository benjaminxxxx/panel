<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Facturas sujetas al SPOT: código del bien o servicio, porcentaje, monto y cuenta del Banco de la Nación.
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->json('detraccion')->nullable()->after('informacion_adicional');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn('detraccion');
        });
    }
};
