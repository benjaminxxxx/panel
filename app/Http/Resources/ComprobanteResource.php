<?php

namespace App\Http\Resources;

use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Comprobante
 */
class ComprobanteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'referencia_externa' => $this->referencia_externa,
            'sistema' => $this->sistema,
            'entorno' => $this->entorno->value,
            'tipo_comprobante' => $this->tipo_comprobante->value,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero' => $this->numero(),
            // Solo en notas de crédito: el comprobante que modifica y por qué.
            'comprobante_afectado' => $this->afectado ? [
                'id' => $this->afectado->uuid,
                'tipo_comprobante' => $this->afectado->tipo_comprobante->value,
                'numero' => $this->afectado->numero(),
            ] : null,
            'motivo' => $this->motivo_codigo ? [
                'codigo' => $this->motivo_codigo,
                'descripcion' => $this->motivo_descripcion,
            ] : null,
            'informacion_adicional' => $this->informacion_adicional ?? [],
            // Solo en facturas sujetas al SPOT: {codigo, porcentaje, monto, cuenta, medio_pago}.
            'detraccion' => $this->detraccion,
            'fecha_emision' => $this->fecha_emision->setTimezone('America/Lima')->toIso8601String(),
            'moneda' => $this->moneda,
            'estado' => $this->estado->value,
            'valido' => $this->estado->esValido(),
            'sunat' => [
                'codigo' => $this->sunat_codigo,
                'descripcion' => $this->sunat_descripcion,
                'notas' => $this->sunat_notas ?? [],
            ],
            'hash' => $this->hash,
            'emisor' => [
                'ruc' => $this->empresa->ruc,
                'razon_social' => $this->empresa->razon_social,
            ],
            'cliente' => [
                'tipo_documento' => $this->cliente_tipo_documento,
                'numero_documento' => $this->cliente_numero_documento,
                'razon_social' => $this->cliente_razon_social,
                'direccion' => $this->cliente_direccion,
                'email' => $this->cliente_email,
            ],
            'totales' => [
                'op_gravadas' => $this->op_gravadas,
                'op_exoneradas' => $this->op_exoneradas,
                'op_inafectas' => $this->op_inafectas,
                'igv' => $this->igv,
                'total_impuestos' => $this->total_impuestos,
                'valor_venta' => $this->valor_venta,
                'sub_total' => $this->sub_total,
                'total' => $this->total,
                'monto_letras' => $this->monto_letras,
            ],
            'items' => $this->items->map(fn (ComprobanteItem $item) => [
                'orden' => $item->orden,
                'codigo' => $item->codigo,
                'descripcion' => $item->descripcion,
                'unidad' => $item->unidad,
                'cantidad' => $item->cantidad,
                'valor_unitario' => $item->valor_unitario,
                'precio_unitario' => $item->precio_unitario,
                'tipo_afectacion_igv' => $item->tipo_afectacion_igv,
                'porcentaje_igv' => $item->porcentaje_igv,
                'valor_venta' => $item->valor_venta,
                'igv' => $item->igv,
                'importe_total' => $item->importe_total,
            ]),
            'archivos' => [
                'xml' => $this->xml_path ? route('api.comprobantes.xml', $this->resource) : null,
                'cdr' => $this->cdr_path ? route('api.comprobantes.cdr', $this->resource) : null,
                'pdf' => route('api.comprobantes.pdf', $this->resource),
                'ticket' => route('api.comprobantes.pdf', [$this->resource, 'formato' => 'ticket']),
                // PDF A4 sin token, para el cliente final (WhatsApp). La firma impide adivinar otros.
                'publico' => URL::signedRoute('comprobantes.publico', $this->resource),
            ],
            'intentos_envio' => $this->intentos_envio,
            'enviado_at' => $this->enviado_at?->toIso8601String(),
        ];
    }
}
