<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Padrón reducido del RUC: archivo oficial que SUNAT publica para descarga libre.
        // Se importa con "php artisan consultas:padron" y responde las consultas de RUC al instante.
        Schema::create('padron_ruc', function (Blueprint $table) {
            $table->char('ruc', 11)->primary();
            $table->string('razon_social', 250);
            $table->string('estado', 40);
            $table->string('condicion', 40);
            $table->char('ubigeo', 6)->nullable();
            $table->string('direccion', 300)->default('');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padron_ruc');
    }
};
