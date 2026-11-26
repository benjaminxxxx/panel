<?php

namespace App\Licencias;

use App\Models\Cliente;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Firma con Ed25519 lo que el programa del local debe poder creer: su licencia y cada
 * versión publicada. El programa trae solo la llave pública: no puede falsificar nada.
 */
final class Firmador
{
    public function firmar(string $mensaje): string
    {
        $llave = base64_decode((string) config('licencias.llave_privada'), true);
        if (! $llave || strlen($llave) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('Falta LICENCIA_LLAVE_PRIVADA en .env: ejecuta php artisan licencias:llaves');
        }

        return base64_encode(sodium_crypto_sign_detached($mensaje, $llave));
    }

    /** @return array{datos: string, firma: string} */
    public function licencia(Cliente $c): array
    {
        $ahora = Carbon::now()->utc();
        $estado = EstadoCliente::evaluar($c, Carbon::now());
        $datos = json_encode([
            'aplicacion' => $c->aplicacion,
            'cliente' => $c->codigo,
            'nombre' => $c->nombre,
            'estado' => $estado['estado'],
            'motivo' => $estado['motivo'],
            'aviso' => $estado['aviso'],
            'vence_el' => $c->vence_el?->toDateString() ?? '',
            'reinicio' => (string) $c->reinicio_permiso, // permiso de "empezar de cero" (vacío = solo la primera vez)
            'emitida' => $ahora->format('Y-m-d\TH:i:s\Z'),
            'valida_hasta' => $ahora->copy()->addDays(config('licencias.dias_sin_conexion'))->format('Y-m-d\TH:i:s\Z'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return ['datos' => base64_encode($datos), 'firma' => $this->firmar($datos)];
    }

    /** Lo que se firma de cada versión: debe coincidir con actualizador.MensajeFirmado en Go de cada sistema. */
    public static function mensajeVersion(string $version, string $sha256, string $aplicacion = 'restaurante'): string
    {
        return $aplicacion.'|actualizacion|'.$version.'|'.strtolower($sha256);
    }
}
