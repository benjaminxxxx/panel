<?php

namespace App\Http\Controllers\Api;

use App\Catalogo\OpenFoodFacts;
use App\Http\Controllers\Controller;
use App\Models\CatalogoProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catálogo público de productos con código de barras. Lo consultan los programas de los
 * locales (panadería) para descargar productos de marca sin escribirlos a mano.
 * Contrato: panaderia/docs/contrato-panel.md.
 */
class CatalogoController extends Controller
{
    private const POR_PAGINA = 30;

    public function buscar(Request $request): JsonResponse
    {
        $pagina = max(1, (int) $request->query('pagina', 1));
        $consulta = CatalogoProducto::where('activo', true)->buscar((string) $request->query('q', ''));
        $total = (clone $consulta)->count();
        $productos = $consulta->orderBy('nombre')->forPage($pagina, self::POR_PAGINA)->get();

        return response()->json([
            'data' => $productos->map(fn (CatalogoProducto $p) => $this->ficha($p)),
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => self::POR_PAGINA,
        ]);
    }

    /**
     * Un producto por código de barras (escaneo desde la panadería). Si no está en el catálogo
     * pero Open Food Facts lo tiene, se agrega al momento (sea o no de panadería).
     *   200 {producto}          → existe (o se acaba de traer)
     *   404 {error, sugerencia} → nadie lo tiene, o el proveedor lo desactivó (sugerencia = su nombre)
     */
    public function porCodigo(string $codigo, OpenFoodFacts $off): JsonResponse
    {
        if (! preg_match('/^\d{8,14}$/', $codigo)) {
            return response()->json(['error' => 'El código de barras tiene de 8 a 14 números.'], 422);
        }
        $r = $off->buscarPorCodigo($codigo);
        if ($r['producto']) {
            return response()->json(['producto' => $this->ficha($r['producto'])]);
        }

        return response()->json([
            'error' => $r['sugerencia']
                ? 'Este producto no está disponible en el catálogo.'
                : 'Este código no está registrado en ningún catálogo.',
            'sugerencia' => $r['sugerencia'],
        ], 404);
    }

    public function foto(CatalogoProducto $producto): Response
    {
        abort_unless($producto->foto && Storage::disk('local')->exists($producto->foto), 404);

        return Storage::disk('local')->response($producto->foto, null, ['Cache-Control' => 'public, max-age=604800']);
    }

    /** @return array<string, mixed> */
    private function ficha(CatalogoProducto $p): array
    {
        return [
            'codigo_barras' => $p->codigo_barras,
            'nombre' => $p->nombre,
            'marca' => (string) $p->marca,
            'presentacion' => (string) $p->presentacion,
            'categoria' => (string) $p->categoria,
            'unidad' => $p->unidad,
            'precio_sugerido' => $p->precio_sugerido,
            'foto' => $p->foto ? route('api.catalogo.foto', $p) : '',
            'fuente' => $p->fuente ?? 'manual',
        ];
    }
}
