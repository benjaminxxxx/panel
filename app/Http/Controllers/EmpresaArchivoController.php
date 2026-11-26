<?php

namespace App\Http\Controllers;

use App\Facturacion\RepresentacionImpresa;
use App\Models\Comprobante;
use App\Models\Empresa;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga del XML, CDR o PDF de un comprobante desde el panel.
 */
class EmpresaArchivoController extends Controller
{
    public function __invoke(RepresentacionImpresa $impresion, Empresa $empresa, Comprobante $comprobante, string $archivo): Response
    {
        Gate::authorize('administrar', $empresa);
        abort_unless($comprobante->empresa_id === $empresa->id, 404);

        if (in_array($archivo, RepresentacionImpresa::FORMATOS, true)) {
            return response($impresion->pdf($comprobante, $archivo), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$impresion->nombreArchivo($comprobante, $archivo).'"',
            ]);
        }

        [$ruta, $nombre] = $archivo === 'xml'
            ? [$comprobante->xml_path, "{$comprobante->nombreArchivo()}.xml"]
            : [$comprobante->cdr_path, "R-{$comprobante->nombreArchivo()}.zip"];

        abort_if($ruta === null, 404);

        return $empresa->disco()->download($ruta, $nombre);
    }
}
