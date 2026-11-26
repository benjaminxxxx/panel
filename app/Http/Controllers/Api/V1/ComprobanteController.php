<?php

namespace App\Http\Controllers\Api\V1;

use App\Facturacion\Core\Validacion\ComprobanteInvalido;
use App\Facturacion\EmpresaNoHabilitada;
use App\Facturacion\RepresentacionImpresa;
use App\Facturacion\ServicioEmision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EmitirComprobanteRequest;
use App\Http\Requests\Api\EmitirNotaCreditoRequest;
use App\Http\Requests\Api\EmitirNotaDebitoRequest;
use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class ComprobanteController extends Controller
{
    public function __construct(private readonly ServicioEmision $emision) {}

    /**
     * Datos de la empresa vinculada al token: sirve para que el sistema cliente verifique su configuración.
     */
    public function yo(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request);

        return response()->json([
            'sistema' => $request->attributes->get('sistema'),
            'empresa' => [
                'ruc' => $empresa->ruc,
                'razon_social' => $empresa->razon_social,
                'entorno' => $empresa->entorno->value,
                'certificado_vence_en' => $empresa->certificado_vence_en?->toDateString(),
            ],
            'series' => $empresa->series()->where('activo', true)->orderBy('serie')->get()
                ->map(fn ($s) => ['tipo_comprobante' => $s->tipo_comprobante->value, 'serie' => $s->serie, 'ultimo_correlativo' => $s->ultimoCorrelativo($empresa->entorno)]),
        ]);
    }

    /**
     * Emite una factura o boleta. HTTP 201 = creado ahora; 200 = ya existía esa referencia_externa.
     * El resultado ante SUNAT va en "estado" (aceptado | observado | rechazado | error_envio | pendiente).
     */
    public function store(EmitirComprobanteRequest $request): JsonResponse
    {
        try {
            $resultado = $this->emision->emitir($this->empresa($request), $this->sistema($request), $request->validated());
        } catch (ComprobanteInvalido $e) {
            throw ValidationException::withMessages($e->errores);
        } catch (EmpresaNoHabilitada $e) {
            throw ValidationException::withMessages(['empresa' => [$e->getMessage()]]);
        }

        return (new ComprobanteResource($resultado->comprobante->load('empresa', 'items')))
            ->response()
            ->setStatusCode($resultado->nuevo ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Emite una nota de crédito sobre una factura o boleta de la empresa. Sin ítems anula el
     * comprobante completo. Mismas respuestas que la emisión: 201 creado, 200 ya existía.
     */
    public function notaCredito(EmitirNotaCreditoRequest $request, string $uuid): JsonResponse
    {
        $afectado = $this->buscar($request, $uuid)->load('items');

        try {
            $resultado = $this->emision->emitirNotaCredito($this->empresa($request), $this->sistema($request), $afectado, $request->validated());
        } catch (ComprobanteInvalido $e) {
            throw ValidationException::withMessages($e->errores);
        } catch (EmpresaNoHabilitada $e) {
            throw ValidationException::withMessages(['empresa' => [$e->getMessage()]]);
        }

        return (new ComprobanteResource($resultado->comprobante->load('empresa', 'items', 'afectado')))
            ->response()
            ->setStatusCode($resultado->nuevo ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Emite una nota de débito (aumenta lo cobrado) sobre una factura o boleta de la empresa.
     * Siempre lleva ítems. Mismas respuestas que la emisión: 201 creado, 200 ya existía.
     */
    public function notaDebito(EmitirNotaDebitoRequest $request, string $uuid): JsonResponse
    {
        $afectado = $this->buscar($request, $uuid)->load('items');

        try {
            $resultado = $this->emision->emitirNotaDebito($this->empresa($request), $this->sistema($request), $afectado, $request->validated());
        } catch (ComprobanteInvalido $e) {
            throw ValidationException::withMessages($e->errores);
        } catch (EmpresaNoHabilitada $e) {
            throw ValidationException::withMessages(['empresa' => [$e->getMessage()]]);
        }

        return (new ComprobanteResource($resultado->comprobante->load('empresa', 'items', 'afectado')))
            ->response()
            ->setStatusCode($resultado->nuevo ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function show(Request $request, string $uuid): ComprobanteResource
    {
        return new ComprobanteResource($this->buscar($request, $uuid)->load('empresa', 'items'));
    }

    public function reenviar(Request $request, string $uuid): ComprobanteResource
    {
        $comprobante = $this->buscar($request, $uuid);

        try {
            $this->emision->reenviar($comprobante);
        } catch (LogicException|EmpresaNoHabilitada $e) {
            throw ValidationException::withMessages(['comprobante' => [$e->getMessage()]]);
        }

        return new ComprobanteResource($comprobante->load('empresa', 'items'));
    }

    public function xml(Request $request, string $uuid): Response
    {
        $comprobante = $this->buscar($request, $uuid);
        abort_if($comprobante->xml_path === null, 404, 'El comprobante no tiene XML.');

        return $this->emision->disco()->download($comprobante->xml_path, "{$comprobante->nombreArchivo()}.xml", ['Content-Type' => 'application/xml']);
    }

    public function cdr(Request $request, string $uuid): Response
    {
        $comprobante = $this->buscar($request, $uuid);
        abort_if($comprobante->cdr_path === null, 404, 'El comprobante aún no tiene CDR.');

        return $this->emision->disco()->download($comprobante->cdr_path, "R-{$comprobante->nombreArchivo()}.zip", ['Content-Type' => 'application/zip']);
    }

    /**
     * PDF de la representación impresa: ?formato=a4 (por defecto) o ?formato=ticket (80 mm).
     */
    public function pdf(Request $request, string $uuid, RepresentacionImpresa $impresion): Response
    {
        $comprobante = $this->buscar($request, $uuid);
        $formato = (string) $request->query('formato', 'a4');
        abort_unless(in_array($formato, RepresentacionImpresa::FORMATOS, true), 422, 'Formato no soportado. Use a4 o ticket.');

        return response($impresion->pdf($comprobante, $formato), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$impresion->nombreArchivo($comprobante, $formato).'"',
        ]);
    }

    /**
     * Un sistema solo ve comprobantes de SU empresa (aunque adivine un uuid ajeno).
     */
    private function buscar(Request $request, string $uuid): Comprobante
    {
        return Comprobante::where('empresa_id', $this->empresa($request)->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function empresa(Request $request): Empresa
    {
        return $request->attributes->get('empresa');
    }

    private function sistema(Request $request): string
    {
        return $request->attributes->get('sistema');
    }
}
