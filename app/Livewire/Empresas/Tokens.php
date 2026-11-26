<?php

namespace App\Livewire\Empresas;

use App\Models\Empresa;
use App\Models\EmpresaToken;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Tokens extends Component
{
    public Empresa $empresa;

    public string $sistema = 'pos';

    public string $nombre = '';

    /** Token en claro recién creado: se muestra una sola vez y no se guarda. */
    public ?string $tokenNuevo = null;

    public function mount(Empresa $empresa): void
    {
        Gate::authorize('administrar', $empresa);

        $this->empresa = $empresa;
    }

    /**
     * @return Collection<int, EmpresaToken>
     */
    #[Computed]
    public function tokens(): Collection
    {
        return $this->empresa->tokens()->orderByRaw('revocado_at is not null')->latest()->get();
    }

    public function crear(): void
    {
        Gate::authorize('administrar', $this->empresa);

        $this->validate([
            'sistema' => ['required', Rule::in(array_keys(config('facturacion.sistemas')))],
            'nombre' => ['nullable', 'string', 'max:100'],
        ]);

        $etiqueta = trim($this->nombre) ?: config("facturacion.sistemas.{$this->sistema}");
        [, $this->tokenNuevo] = EmpresaToken::generar($this->empresa, $this->sistema, $etiqueta);

        $this->reset('nombre');
        unset($this->tokens);

        Flux::modal('token-nuevo')->show();
        $this->dispatch('empresa-actualizada');
    }

    public function cerrarToken(): void
    {
        $this->tokenNuevo = null;
        Flux::modal('token-nuevo')->close();
    }

    public function revocar(int $tokenId): void
    {
        Gate::authorize('administrar', $this->empresa);

        $this->empresa->tokens()->whereKey($tokenId)->whereNull('revocado_at')->update(['revocado_at' => now()]);
        unset($this->tokens);

        Flux::toast(text: 'Token revocado. El sistema que lo usaba ya no podrá emitir.');
        $this->dispatch('empresa-actualizada');
    }
}
