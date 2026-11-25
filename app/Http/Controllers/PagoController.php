<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    /** Registrar un pago extiende el vencimiento desde la fecha que sea mayor: hoy o el vencimiento actual. */
    public function store(Request $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0', 'max:100000'],
            'meses' => ['required', 'integer', 'min:1', 'max:24'],
            'nota' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($cliente, $datos) {
            $cliente->pagos()->create($datos + ['pagado_el' => today()]);
            $desde = $cliente->vence_el && $cliente->vence_el->gt(today()) ? $cliente->vence_el : Carbon::today();
            $cliente->update(['vence_el' => $desde->copy()->addMonthsNoOverflow((int) $datos['meses'])]);
        });

        return back()->with('ok', 'Pago registrado. Nuevo vencimiento: '.$cliente->fresh()->vence_el->format('d/m/Y'));
    }
}
