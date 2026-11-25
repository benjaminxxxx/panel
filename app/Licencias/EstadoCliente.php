<?php

namespace App\Licencias;

use App\Models\Cliente;
use Carbon\CarbonInterface;

/**
 * Decide si un cliente está activo o bloqueado hoy, y qué aviso ve.
 * Una sola regla, usada por la licencia firmada y por las pantallas del panel.
 */
final class EstadoCliente
{
    /** @return array{estado: string, motivo: string, aviso: string, vencido: bool} */
    public static function evaluar(Cliente $c, CarbonInterface $ahora): array
    {
        $hoy = $ahora->copy()->startOfDay();
        $r = ['estado' => 'activo', 'motivo' => '', 'aviso' => '', 'vencido' => false];

        if ($c->estado === 'bloqueado') {
            return ['estado' => 'bloqueado', 'motivo' => $c->motivo_bloqueo ?: config('licencias.motivo_por_defecto')] + $r;
        }
        if (! $c->vence_el) {
            return $r;
        }

        $vence = $c->vence_el->copy()->startOfDay();
        $limite = $vence->copy()->addDays(config('licencias.dias_gracia'));
        $r['vencido'] = $hoy->gt($vence);

        if ($c->bloqueo_automatico && $hoy->gt($limite)) {
            return ['estado' => 'bloqueado', 'motivo' => sprintf(
                'Tu suscripción venció el %s. %s', $vence->format('d/m/Y'), config('licencias.motivo_por_defecto')
            )] + $r;
        }
        if ($r['vencido']) {
            $r['aviso'] = $c->bloqueo_automatico
                ? sprintf('Tu pago venció el %s. El servicio se suspenderá el %s.', $vence->format('d/m/Y'), $limite->copy()->addDay()->format('d/m/Y'))
                : sprintf('Tu pago venció el %s.', $vence->format('d/m/Y'));
        } elseif ($hoy->diffInDays($vence) <= config('licencias.dias_aviso')) {
            $r['aviso'] = sprintf('Tu pago vence el %s.', $vence->format('d/m/Y'));
        }

        return $r;
    }
}
