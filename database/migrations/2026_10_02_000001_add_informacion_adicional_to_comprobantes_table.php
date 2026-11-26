<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos que el negocio quiere imprimir en su comprobante (cuentas bancarias, teléfonos,
     * condiciones...). Solo van en la representación impresa: no forman parte del XML de SUNAT.
     */
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            // Lista de {ubicacion: cabecera|centro|pie, etiqueta: ?string, valor: string}.
            $table->json('informacion_adicional')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn('informacion_adicional');
        });
    }
};
