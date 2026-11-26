<?php

namespace App\Http\Requests\Api;

use App\Facturacion\Core\Enums\TipoComprobante;
use App\Facturacion\Core\Enums\TipoDocumentoIdentidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación estructural (tipos y presencia). Las reglas de negocio/SUNAT viven en el core.
 */
class EmitirComprobanteRequest extends FormRequest
{
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
            // La nota de crédito tiene su propio endpoint: siempre nace de un comprobante existente.
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)->only(TipoComprobante::ventas())],
            'serie' => ['required', 'string', 'size:4'],
            'fecha_emision' => ['nullable', 'date'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'observacion' => ['nullable', 'string'],

            // Solo para la representación impresa (no viaja a SUNAT): cuentas bancarias, teléfonos, condiciones...
            'informacion_adicional' => ['nullable', 'array', 'max:12'],
            'informacion_adicional.*.ubicacion' => ['required', Rule::in(['cabecera', 'centro', 'pie'])],
            'informacion_adicional.*.etiqueta' => ['nullable', 'string', 'max:60'],
            'informacion_adicional.*.valor' => ['required', 'string', 'max:250'],

            'cliente' => [Rule::requiredIf($this->input('tipo_comprobante') === TipoComprobante::Factura->value), 'nullable', 'array'],
            'cliente.tipo_documento' => ['required_with:cliente', 'nullable', Rule::enum(TipoDocumentoIdentidad::class)],
            'cliente.numero_documento' => ['required_with:cliente', 'nullable', 'string', 'max:15'],
            'cliente.razon_social' => ['required_with:cliente', 'nullable', 'string'],
            'cliente.direccion' => ['nullable', 'string', 'max:255'],
            'cliente.email' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string'],
            'items.*.cantidad' => ['required', 'numeric'],
            'items.*.unidad' => ['nullable', 'string', 'max:3'],
            'items.*.codigo' => ['nullable', 'string'],
            'items.*.precio_unitario' => ['nullable', 'numeric'],
            'items.*.valor_unitario' => ['nullable', 'numeric'],
            'items.*.importe' => ['nullable', 'numeric'],
            'items.*.afectacion_igv' => ['nullable', 'string', 'in:10,20,30'],
            'items.*.porcentaje_igv' => ['nullable', 'numeric'],

            // Factura sujeta a detracción (SPOT): código del catálogo 54, porcentaje (por defecto el del código) y cuenta del Banco de la Nación.
            'detraccion' => ['nullable', 'array'],
            'detraccion.codigo' => ['required_with:detraccion', 'string', 'size:3'],
            'detraccion.porcentaje' => ['nullable', 'numeric'],
            'detraccion.cuenta' => ['required_with:detraccion', 'string', 'max:30'],
        ];
    }
}
