<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->string('sistema', 30);
            // Id de la venta en el sistema origen: hace idempotente el POST (reintentos no duplican comprobantes).
            $table->string('referencia_externa', 100);
            $table->string('entorno', 12);

            $table->string('tipo_comprobante', 2);
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->dateTime('fecha_emision');
            $table->string('moneda', 3)->default('PEN');

            $table->string('cliente_tipo_documento', 1);
            $table->string('cliente_numero_documento', 15);
            $table->string('cliente_razon_social', 500);
            $table->string('cliente_direccion')->nullable();
            $table->string('cliente_email')->nullable();

            $table->decimal('op_gravadas', 14, 2);
            $table->decimal('op_exoneradas', 14, 2)->default(0);
            $table->decimal('op_inafectas', 14, 2)->default(0);
            $table->decimal('igv', 14, 2);
            $table->decimal('total_impuestos', 14, 2);
            $table->decimal('valor_venta', 14, 2);
            $table->decimal('sub_total', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('monto_letras');
            $table->string('observacion', 500)->nullable();

            $table->string('estado', 20)->index();
            $table->string('sunat_codigo', 10)->nullable();
            $table->text('sunat_descripcion')->nullable();
            $table->json('sunat_notas')->nullable();
            $table->string('hash', 100)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->unsignedSmallInteger('intentos_envio')->default(0);
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo_comprobante', 'serie', 'correlativo'], 'comprobantes_numero_unique');
            $table->unique(['empresa_id', 'sistema', 'referencia_externa'], 'comprobantes_referencia_unique');
        });

        Schema::create('comprobante_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('codigo', 30)->nullable();
            $table->string('descripcion', 500);
            $table->string('unidad', 3);
            $table->decimal('cantidad', 18, 6);
            $table->decimal('valor_unitario', 22, 10);
            $table->decimal('precio_unitario', 22, 10);
            $table->string('tipo_afectacion_igv', 2);
            $table->decimal('porcentaje_igv', 5, 2);
            $table->decimal('valor_venta', 14, 2);
            $table->decimal('base_igv', 14, 2);
            $table->decimal('igv', 14, 2);
            $table->decimal('total_impuestos', 14, 2);
            $table->decimal('importe_total', 14, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_items');
        Schema::dropIfExists('comprobantes');
    }
};
