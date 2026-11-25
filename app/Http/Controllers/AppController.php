<?php

namespace App\Http\Controllers;

use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo que abre el QR del local (Panel → Celulares): si el celular tiene la app, la abre ya
 * conectada al servidor del local; si no, ofrece descargarla. Páginas públicas: el APK no
 * tiene nada secreto (sin el servidor del local no muestra nada).
 */
class AppController extends Controller
{
    public const PAQUETE = 'pe.restaurante.app';

    public function abrir(Request $request): View
    {
        $servidor = $this->servidorValido($request->query('servidor'));
        $esAndroid = str_contains(strtolower((string) $request->userAgent()), 'android');
        $apk = Version::ultimaPublicada('android');
        $instalar = $request->boolean('instalar');

        $enlaceApp = null;
        if ($servidor) {
            $datos = http_build_query(['servidor' => $servidor, 'panel' => url('/')]);
            $volver = url('/app').'?'.http_build_query(['servidor' => $servidor, 'instalar' => 1]);
            // Chrome abre la app si está instalada; si no, va a la página para instalarla.
            $enlaceApp = 'intent://abrir?'.$datos.'#Intent;scheme=restaurante;package='.self::PAQUETE
                .';S.browser_fallback_url='.rawurlencode($volver).';end';
        }

        return view('app.abrir', compact('servidor', 'esAndroid', 'apk', 'instalar', 'enlaceApp'));
    }

    public function descargar(): StreamedResponse
    {
        $v = Version::ultimaPublicada('android');
        abort_unless($v, 404, 'Aún no hay app Android publicada.');

        return Storage::disk('local')->download($v->archivo, 'Restaurante.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    /** La app pregunta esto al abrirse para saber si debe actualizarse. */
    public function ultima(): JsonResponse
    {
        $v = Version::ultimaPublicada('android');
        if (! $v) {
            return response()->json(['error' => 'sin versiones'], 404);
        }

        return response()->json([
            'version' => $v->version,
            'version_code' => $v->codigoAndroid(),
            'notas' => (string) $v->notas,
            'sha256' => $v->sha256,
            'tamano' => $v->tamano,
            'url' => url('/app/android/descargar'),
        ]);
    }

    /** Solo direcciones http(s) con host: evita que el QR inyecte otra cosa. */
    private function servidorValido(mixed $s): ?string
    {
        if (! is_string($s) || ! filter_var($s, FILTER_VALIDATE_URL)) {
            return null;
        }
        $esquema = parse_url($s, PHP_URL_SCHEME);

        return in_array($esquema, ['http', 'https'], true) && parse_url($s, PHP_URL_HOST) ? rtrim($s, '/') : null;
    }
}
