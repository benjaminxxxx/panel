<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ruc', 11)->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('email')->nullable();

            // Domicilio fiscal (obligatorio en el XML)
            $table->string('direccion');
            $table->string('ubigeo', 6);
            $table->string('departamento');
            $table->string('provincia');
            $table->string('distrito');
            $table->string('urbanizacion')->nullable();
            $table->string('codigo_local', 4)->default('0000');

            // Conexión SUNAT (secretos cifrados con APP_KEY)
            $table->string('entorno', 12)->default('beta');
            $table->string('sol_usuario')->nullable();
            $table->text('sol_clave')->nullable();
            $table->text('certificado_pem')->nullable();
            $table->timestamp('certificado_vence_en')->nullable();
            $table->string('certificado_sujeto')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
