<?php

namespace App\Livewire\Empresas;

use App\Facturacion\Core\Enums\TipoComprobante;
use App\Models\Empresa;
use App\Models\Serie;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Series extends Component
{
    public Empresa $empresa;

    public string $tipo = '01';

    public string $serie = 'F001';

    public int $ultimo_correlativo = 0;

    public function mount(Empresa $empresa): void
    {
        Gate::authorize('administrar', $empresa);

        $this->empresa = $empresa;
    }

    public function updatedTipo(string $tipo): void
    {
        $tipoComprobante = TipoComprobante::tryFrom($tipo);

        // Sugerencia: F001 / B001; FC01 o FD01 para notas de facturas (BC01 o BD01 para las de boletas).
        $this->serie = $tipoComprobante?->serieEjemplo($tipoComprobante->prefijoSerie()) ?? 'F001';
    }

    /**
     * @return Collection<int, Serie>
     */
    #[Computed]
    public function series(): Collection
    {
        return $this->empresa->series()->orderBy('tipo_comprobante')->orderBy('serie')->get();
    }

    public function agregar(): void
    {
        Gate::authorize('administrar', $this->empresa);

        $this->serie = strtoupper(trim($this->serie));
        $tipo = TipoComprobante::tryFrom($this->tipo);

        $this->validate([
            'tipo' => ['required', Rule::enum(TipoComprobante::class)],
            'serie' => [
                'required',
                'regex:'.($tipo?->patronSerie() ?? '/^[FB][A-Z0-9]{3}$/'),
                Rule::unique('series', 'serie')->where('empresa_id', $this->empresa->id)->where('tipo_comprobante', $this->tipo),
            ],
            'ultimo_correlativo' => ['required', 'integer', 'min:0', 'max:99999999'],
        ], [
            'serie.regex' => $tipo?->esNota()
                ? "La serie debe tener 4 caracteres y empezar con F (notas de facturas, ej: {$tipo->serieEjemplo('F')}) o con B (de boletas, ej: {$tipo->serieEjemplo('B')})."
                : 'La serie debe tener 4 caracteres y empezar con '.($tipo?->prefijoSerie() ?? 'F o B').' (ej: '.($tipo?->prefijoSerie() ?? 'F').'001).',
            'serie.unique' => 'Esa serie ya está registrada.',
        ]);

        Serie::create([
            'empresa_id' => $this->empresa->id,
            'tipo_comprobante' => $tipo,
            'serie' => $this->serie,
            'correlativo' => $this->ultimo_correlativo,
        ]);

        $this->reset('ultimo_correlativo');
        unset($this->series);

        Flux::toast(variant: 'success', text: "Serie {$this->serie} registrada.");
        $this->dispatch('empresa-actualizada');
    }

    public function alternar(int $serieId): void
    {
        Gate::authorize('administrar', $this->empresa);

        $serie = $this->empresa->series()->findOrFail($serieId);
        $serie->update(['activo' => ! $serie->activo]);
        unset($this->series);

        $this->dispatch('empresa-actualizada');
    }
}
