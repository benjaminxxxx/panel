<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Licencias\Firmador;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenciaController extends Controller
{
    public function mostrar(Request $request, Firmador $firmador): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->attributes->get('cliente');
        $cliente->forceFill([
            'ultimo_contacto_at' => now(),
            'version_reportada' => substr((string) $request->header('X-Version'), 0, 40) ?: $cliente->version_reportada,
            'ip_reportada' => $request->ip(),
        ]);
        // El local avisa que ya usó el permiso de "empezar de cero": se consume.
        $usado = (string) $request->header('X-Reinicio-Usado');
        if ($usado !== '' && $cliente->reinicio_permiso !== null && hash_equals($cliente->reinicio_permiso, $usado)) {
            $cliente->reinicio_permiso = null;
        }
        $cliente->save();

        return response()->json($firmador->licencia($cliente));
    }
}
