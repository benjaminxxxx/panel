<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('versiones')]
#[Fillable(['version', 'notas', 'archivo', 'sha256', 'firma', 'tamano', 'publicada'])]
class Version extends Model
{
    protected function casts(): array
    {
        return ['publicada' => 'boolean', 'tamano' => 'integer'];
    }

    /** La versión publicada más nueva (compara 1.0.10 > 1.0.9). */
    public static function ultimaPublicada(): ?self
    {
        return static::where('publicada', true)->get()
            ->sort(fn (self $a, self $b) => version_compare($b->version, $a->version))
            ->first();
    }
}
