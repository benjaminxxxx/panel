<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;

class EmpresaPolicy
{
    /**
     * Por ahora cada empresa tiene un único dueño: la cuenta que la registró.
     */
    public function administrar(User $user, Empresa $empresa): bool
    {
        return $empresa->user_id === $user->id;
    }
}
