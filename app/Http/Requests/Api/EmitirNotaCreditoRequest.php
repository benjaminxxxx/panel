<?php

namespace App\Http\Requests\Api;

use App\Facturacion\Core\Enums\MotivoNotaCredito;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación estructural de una nota de crédito. Qué comprobante se puede acreditar y por
 * cuánto lo decide el servicio de emisión.
 */
class EmitirNotaCreditoRequest extends FormRequest
{
    /** @var class-string<\BackedEnum> Catálogo de motivos que acepta este tipo de nota. */
    protected const MOTIVOS = MotivoNotaCredito::class;

    protected const ITEMS_OBLIGATORIOS = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referencia_externa' => ['required', 'string', 'max:100'],
            'motivo_codigo' => ['nullable', Rule::enum(static::MOTIVOS)],
            'motivo' => ['nullable', 'string', 'max:250'],
            'serie' => ['nullable', 'string', 'size:4'],
            'fecha_emision' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string'],

            // Solo para la representación impresa (no viaja a SUNAT): cuentas bancarias, teléfonos, condiciones...
            'informacion_adicional' => ['nullable', 'array', 'max:12'],
            'informacion_adicional.*.ubicacion' => ['required', Rule::in(['cabecera', 'centro', 'pie'])],
            'informacion_adicional.*.etiqueta' => ['nullable', 'string', 'max:60'],
            'informacion_adicional.*.valor' => ['required', 'string', 'max:250'],

            // Con los motivos parciales (devolución o descuento por ítem, disminución en el valor); sin ítems se revierte el comprobante completo.
            'items' => [static::ITEMS_OBLIGATORIOS ? 'required' : 'nullable', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string'],
            'items.*.cantidad' => ['required', 'numeric'],
            'items.*.unidad' => ['nullable', 'string', 'max:3'],
            'items.*.codigo' => ['nullable', 'string'],
            'items.*.precio_unitario' => ['nullable', 'numeric'],
            'items.*.valor_unitario' => ['nullable', 'numeric'],
            'items.*.importe' => ['nullable', 'numeric'],
            'items.*.afectacion_igv' => ['nullable', 'string', 'in:10,20,30'],
            'items.*.porcentaje_igv' => ['nullable', 'numeric'],
        ];
    }
}
