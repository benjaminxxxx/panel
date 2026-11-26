<?php

namespace App\Livewire\Empresas;

use App\Models\Comprobante;
use App\Models\Empresa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Comprobantes extends Component
{
    use WithPagination;

    public Empresa $empresa;

    public function mount(Empresa $empresa): void
    {
        Gate::authorize('administrar', $empresa);

        $this->empresa = $empresa;
    }

    /**
     * @return LengthAwarePaginator<int, Comprobante>
     */
    #[Computed]
    public function comprobantes(): LengthAwarePaginator
    {
        return $this->empresa->comprobantes()->with('afectado')->latest('id')->paginate(15);
    }
}
