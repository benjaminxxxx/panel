<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De dónde vino el producto: cargado por el proveedor o traído de Open Food Facts (ODbL, con atribución).
        Schema::table('catalogo_productos', function (Blueprint $table) {
            $table->string('fuente', 20)->default('manual')->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_productos', function (Blueprint $table) {
            $table->dropColumn('fuente');
        });
    }
};
