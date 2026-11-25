<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Código de activación de un solo uso: el instalador es igual para todos los clientes;
        // al instalar, el local pide este código y recibe su clave.
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('activacion_hash', 64)->nullable()->unique();
            $table->timestamp('activacion_expira_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['activacion_hash']);
            $table->dropColumn(['activacion_hash', 'activacion_expira_at']);
        });
    }
};
