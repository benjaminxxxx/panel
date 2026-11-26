<?php

namespace App\Http\Controllers;

use App\Facturacion\RepresentacionImpresa;
use App\Models\Comprobante;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF A4 de un comprobante para el cliente final, sin iniciar sesión ni token.
 * Solo se abre con el enlace firmado que devuelve la API (archivos.publico): quien no
 * tiene el enlace no puede adivinarlo. Lo usan los sistemas para mandarlo por WhatsApp.
 */
class ComprobantePublicoController extends Controller
{
    public function __invoke(RepresentacionImpresa $impresion, Comprobante $comprobante): Response
    {
        return response($impresion->pdf($comprobante, 'a4'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$impresion->nombreArchivo($comprobante, 'a4').'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
