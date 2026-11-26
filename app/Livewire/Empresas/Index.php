<?php

namespace App\Livewire\Empresas;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    /**
     * @return Collection<int, Empresa>
     */
    #[Computed]
    public function empresas(): Collection
    {
        return Empresa::where('user_id', Auth::id())
            ->withCount(['comprobantes', 'tokens' => fn ($q) => $q->whereNull('revocado_at')])
            ->orderBy('razon_social')
            ->get();
    }
}
