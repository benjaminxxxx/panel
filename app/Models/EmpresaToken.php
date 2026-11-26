<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Token de API que vincula un sistema (pos, hotel, ...) con una empresa.
 * Solo se guarda el hash SHA-256: el token en claro se muestra una única vez.
 *
 * @property int $id
 * @property int $empresa_id
 * @property string $sistema
 * @property string $nombre
 * @property string $token_hash
 * @property Carbon|null $ultimo_uso_at
 * @property Carbon|null $revocado_at
 * @property-read Empresa $empresa
 */
#[Fillable(['empresa_id', 'sistema', 'nombre', 'token_hash'])]
#[Hidden(['token_hash'])]
class EmpresaToken extends Model
{
    public const PREFIJO = 'fk_';

    protected function casts(): array
    {
        return [
            'ultimo_uso_at' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return array{0: EmpresaToken, 1: string} [modelo, token en claro]
     */
    public static function generar(Empresa $empresa, string $sistema, string $nombre): array
    {
        $plano = self::PREFIJO.Str::random(48);

        $token = self::create([
            'empresa_id' => $empresa->id,
            'sistema' => $sistema,
            'nombre' => $nombre,
            'token_hash' => hash('sha256', $plano),
        ]);

        return [$token, $plano];
    }

    public static function buscarActivo(string $plano): ?self
    {
        if (! str_starts_with($plano, self::PREFIJO)) {
            return null;
        }

        return self::with('empresa')
            ->where('token_hash', hash('sha256', $plano))
            ->whereNull('revocado_at')
            ->first();
    }
}
