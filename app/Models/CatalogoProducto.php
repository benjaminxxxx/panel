<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Producto de marca con código de barras del catálogo público (leche, café, gaseosas...).
 * Los locales lo buscan y lo descargan a su caja con su propio precio.
 *
 * @property int $id
 * @property string $codigo_barras
 * @property string $nombre
 * @property string|null $marca
 * @property string|null $presentacion
 * @property string|null $categoria
 * @property string $unidad
 * @property string|null $precio_sugerido
 * @property string|null $foto
 * @property bool $activo
 */
#[Table('catalogo_productos')]
#[Fillable(['codigo_barras', 'nombre', 'marca', 'presentacion', 'categoria', 'unidad', 'precio_sugerido', 'foto', 'fuente', 'activo'])]
class CatalogoProducto extends Model
{
    public const UNIDADES = ['NIU' => 'Unidad', 'KGM' => 'Kilo', 'LTR' => 'Litro', 'BX' => 'Caja', 'DZN' => 'Docena', 'GRM' => 'Gramo'];

    protected $attributes = ['unidad' => 'NIU', 'activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'precio_sugerido' => 'decimal:2'];
    }

    /**
     * Busca por código (exacto o inicio) o por palabras en nombre, marca, categoría o presentación.
     *
     * @param  Builder<CatalogoProducto>  $q
     * @return Builder<CatalogoProducto>
     */
    public function scopeBuscar(Builder $q, string $texto): Builder
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $q;
        }
        if (preg_match('/^\d{6,}$/', $texto)) {
            return $q->where('codigo_barras', 'like', $texto.'%');
        }
        foreach (preg_split('/\s+/', $texto) ?: [] as $palabra) {
            $q->where(fn (Builder $w) => $w->where('nombre', 'like', "%{$palabra}%")
                ->orWhere('marca', 'like', "%{$palabra}%")
                ->orWhere('categoria', 'like', "%{$palabra}%")
                ->orWhere('presentacion', 'like', "%{$palabra}%"));
        }

        return $q;
    }
}
