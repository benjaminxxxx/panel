<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Windows (instalador del servidor del local) y Android (app de las tablets) se
        // publican por separado, cada uno con su propia numeración.
        Schema::table('versiones', function (Blueprint $table) {
            $table->string('plataforma', 10)->default('windows')->after('id');
            $table->dropUnique(['version']);
            $table->unique(['plataforma', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('versiones', function (Blueprint $table) {
            $table->dropUnique(['plataforma', 'version']);
            $table->unique(['version']);
            $table->dropColumn('plataforma');
        });
    }
};
