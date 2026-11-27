<?php

namespace App\Http\Controllers\Api\V1;

use App\Facturacion\Core\Enums\TipoComprobante;
use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\Serie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Numeración de las series desde el sistema conectado: cuando la empresa emitió comprobantes
 * fuera de él (la contadora, otro sistema), la caja corrige aquí el último número usado sin
 * entrar al panel. Nunca se puede bajar de lo ya emitido por este sistema: duplicaría números.
 */
class SerieController extends Controller
{
    public function update(Request $request, string $serie): JsonResponse
    {
        /** @var Empresa $empresa */
        $empresa = $request->attributes->get('empresa');
        $datos = $request->validate([
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)],
            'ultimo_correlativo' => ['required', 'integer', 'min:0', 'max:99999999'],
        ]);
        $columna = Serie::columnaCorrelativo($empresa->entorno);

        $registro = DB::transaction(function () use ($empresa, $serie, $datos, $columna) {
            $registro = Serie::where('empresa_id', $empresa->id)
                ->where('tipo_comprobante', $datos['tipo_comprobante'])
                ->where('serie', strtoupper($serie))
                ->where('activo', true)
                ->lockForUpdate()
                ->first();
            if (! $registro) {
                abort(404, "La serie {$serie} no está registrada (o está desactivada) en esta empresa.");
            }

            $emitido = (int) Comprobante::where('empresa_id', $empresa->id)
                ->where('tipo_comprobante', $datos['tipo_comprobante'])
                ->where('serie', $registro->serie)
                ->where('entorno', $empresa->entorno->value)
                ->max('correlativo');
            if ($datos['ultimo_correlativo'] < $emitido) {
                throw ValidationException::withMessages(['ultimo_correlativo' => [
                    "El {$registro->serie}-{$emitido} ya fue emitido desde este sistema: el último número no puede ser menor.",
                ]]);
            }

            $registro->update([$columna => $datos['ultimo_correlativo']]);

            return $registro;
        });

        return response()->json([
            'tipo_comprobante' => $registro->tipo_comprobante->value,
            'serie' => $registro->serie,
            'ultimo_correlativo' => $registro->ultimoCorrelativo($empresa->entorno),
        ]);
    }
}
