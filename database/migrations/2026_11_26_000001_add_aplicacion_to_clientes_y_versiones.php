<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El panel atiende varios sistemas (restaurante, panadería). Cada cliente usa uno y
        // cada versión es de uno: un local solo ve las actualizaciones de su sistema.
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('aplicacion', 20)->default('restaurante')->after('id')->index();
        });
        Schema::table('versiones', function (Blueprint $table) {
            $table->string('aplicacion', 20)->default('restaurante')->after('id');
            $table->dropUnique(['plataforma', 'version']);
            $table->unique(['aplicacion', 'plataforma', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('versiones', function (Blueprint $table) {
            $table->dropUnique(['aplicacion', 'plataforma', 'version']);
            $table->unique(['plataforma', 'version']);
            $table->dropColumn('aplicacion');
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['aplicacion']);
            $table->dropColumn('aplicacion');
        });
    }
};
