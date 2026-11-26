<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Panel único: el proveedor (es_admin) ve los locales, versiones y catálogo; los dueños de
        // empresas que se registran solo ven su facturación. Quienes ya estaban en el panel son el proveedor.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_admin')->default(false)->after('email');
        });
        DB::table('users')->update(['es_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('es_admin');
        });
    }
};
