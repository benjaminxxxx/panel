<?php

namespace App\Http\Controllers;

use App\Licencias\EstadoCliente;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClienteController extends Controller
{
    /** Tablero: todos los clientes con su estado efectivo y alertas de pago. */
    public function index(): View
    {
        $clientes = Cliente::orderBy('nombre')->get()->map(fn (Cliente $c) => [
            'cliente' => $c,
            'estado' => EstadoCliente::evaluar($c, now()),
        ]);

        return view('clientes.index', [
            'clientes' => $clientes,
            'alertas' => $clientes->filter(fn ($f) => $f['estado']['estado'] === 'bloqueado' || $f['estado']['aviso'] !== ''),
        ]);
    }

    public function create(): View
    {
        return view('clientes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'vence_el' => ['nullable', 'date'],
        ]);
        $c = new Cliente($datos + ['codigo' => $this->codigoUnico($datos['nombre'])]);
        $clave = $c->nuevaClave();
        $c->save();

        return redirect()->route('clientes.show', $c)->with('clave', $clave);
    }

    public function show(Cliente $cliente): View
    {
        return view('clientes.show', [
            'c' => $cliente->load('pagos'),
            'estado' => EstadoCliente::evaluar($cliente, now()),
        ]);
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'vence_el' => ['nullable', 'date'],
        ]);
        $cliente->update($datos + ['bloqueo_automatico' => $request->boolean('bloqueo_automatico')]);

        return back()->with('ok', 'Datos guardados.');
    }

    public function bloquear(Request $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:250']]);
        $cliente->update(['estado' => 'bloqueado', 'motivo_bloqueo' => $datos['motivo'] ?? null]);

        return back()->with('ok', "{$cliente->nombre} bloqueado. El local lo verá en su próxima verificación (≈1 minuto).");
    }

    public function habilitar(Cliente $cliente): RedirectResponse
    {
        $cliente->update(['estado' => 'activo', 'motivo_bloqueo' => null]);

        return back()->with('ok', "{$cliente->nombre} habilitado.");
    }

    public function regenerarClave(Cliente $cliente): RedirectResponse
    {
        $clave = $cliente->nuevaClave();
        $cliente->save();

        return back()->with('clave', $clave);
    }

    public function activacion(Cliente $cliente): RedirectResponse
    {
        return back()->with('activacion', $cliente->nuevoCodigoActivacion());
    }

    private function codigoUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'cliente';
        $codigo = $base;
        for ($i = 2; Cliente::where('codigo', $codigo)->exists(); $i++) {
            $codigo = "{$base}-{$i}";
        }

        return $codigo;
    }
}
