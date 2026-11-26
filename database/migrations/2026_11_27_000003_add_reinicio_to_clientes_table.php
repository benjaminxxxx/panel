<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Empezar de cero" en el local (borra ventas y productos de prueba) se usa una sola vez.
        // Para volver a usarlo, el proveedor genera aquí un permiso nuevo que viaja en la licencia firmada.
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('reinicio_permiso', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('reinicio_permiso');
        });
    }
};
