<?php

namespace App\Livewire\Empresas;

use App\Facturacion\Core\Sunat\Entorno;
use App\Facturacion\Core\Validacion\Ubigeo;
use App\Models\Empresa;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    public Empresa $empresa;

    public function mount(Empresa $empresa): void
    {
        Gate::authorize('administrar', $empresa);

        $this->empresa = $empresa;
    }

    #[On('empresa-actualizada')]
    public function refrescar(): void
    {
        $this->empresa->refresh();
        unset($this->pasos);
    }

    /**
     * Pasos para quedar lista para emitir, en orden.
     *
     * @return list<array{titulo: string, listo: bool, detalle: string}>
     */
    #[Computed]
    public function pasos(): array
    {
        $empresa = $this->empresa;
        $vence = $empresa->certificado_vence_en;
        $produccion = $empresa->entorno === Entorno::Produccion;

        return [
            [
                'titulo' => 'Datos fiscales',
                'listo' => Ubigeo::esValido($empresa->ubigeo),
                'detalle' => Ubigeo::esValido($empresa->ubigeo)
                    ? "{$empresa->direccion}, {$empresa->distrito}"
                    : "El ubigeo {$empresa->ubigeo} no es válido: SUNAT lo observa en cada comprobante. Corríjalo en Editar datos.",
            ],
            [
                'titulo' => 'Certificado digital',
                'listo' => $vence !== null && $vence->isFuture(),
                'detalle' => match (true) {
                    $vence === null => 'Aún no cargado.',
                    $vence->isPast() => "Venció el {$vence->format('d/m/Y')}.",
                    default => "Vigente hasta el {$vence->format('d/m/Y')} ({$vence->diffForHumans()}).",
                },
            ],
            [
                'titulo' => 'Clave SOL',
                'listo' => ! $produccion || ($empresa->sol_usuario && $empresa->sol_clave),
                'detalle' => $empresa->sol_usuario
                    ? "Usuario {$empresa->sol_usuario}."
                    : ($produccion ? 'Obligatoria en producción.' : 'No se necesita en pruebas (beta).'),
            ],
            [
                'titulo' => 'Series',
                'listo' => $empresa->series()->where('activo', true)->exists(),
                'detalle' => $empresa->series()->where('activo', true)->pluck('serie')->implode(', ') ?: 'Registre al menos una serie (F001, B001).',
            ],
            [
                'titulo' => 'Sistemas conectados',
                'listo' => $empresa->tokens()->whereNull('revocado_at')->exists(),
                'detalle' => $empresa->tokens()->whereNull('revocado_at')->pluck('sistema')->unique()
                    ->map(fn ($s) => config("facturacion.sistemas.{$s}", $s))->implode(', ') ?: 'Genere un token para cada sistema que usará.',
            ],
        ];
    }

    public function render(): mixed
    {
        return view('livewire.empresas.show')->title($this->empresa->razon_social);
    }
}
