<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cliente = un restaurante que alquila el sistema.
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->string('contacto')->nullable();
            $table->string('clave_hash', 64)->unique(); // sha256 de la clave que usa el programa del local
            $table->string('clave_inicio', 8);           // primeros caracteres, para reconocerla
            $table->enum('estado', ['activo', 'bloqueado'])->default('activo'); // bloqueo manual
            $table->string('motivo_bloqueo')->nullable();
            $table->boolean('bloqueo_automatico')->default(true); // bloquear solo si vence y no paga
            $table->date('vence_el')->nullable();
            $table->timestamp('ultimo_contacto_at')->nullable();
            $table->string('version_reportada')->nullable();
            $table->string('ip_reportada')->nullable();
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->decimal('monto', 10, 2);
            $table->unsignedTinyInteger('meses')->default(1);
            $table->date('pagado_el');
            $table->string('nota')->nullable();
            $table->timestamps();
        });

        // Versiones del programa del local, firmadas para que se actualice solo.
        Schema::create('versiones', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->text('notas')->nullable();
            $table->string('archivo');
            $table->string('sha256', 64);
            $table->string('firma');
            $table->unsignedBigInteger('tamano');
            $table->boolean('publicada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('clientes');
    }
};
