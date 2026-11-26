<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo público de productos de marca con código de barras (leche, café...).
        // Lo mantiene el proveedor; los locales lo consultan y descargan lo que venden.
        Schema::create('catalogo_productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_barras', 40)->unique();
            $table->string('nombre', 120);
            $table->string('marca', 80)->nullable();
            $table->string('presentacion', 60)->nullable(); // 400 g, 1 L, lata
            $table->string('categoria', 40)->nullable();
            $table->string('unidad', 3)->default('NIU');
            $table->decimal('precio_sugerido', 10, 2)->nullable();
            $table->string('foto')->nullable(); // ruta en el disco public
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_productos');
    }
};
