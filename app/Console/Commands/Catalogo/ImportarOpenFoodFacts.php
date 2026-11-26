<?php

namespace App\Console\Commands\Catalogo;

use App\Catalogo\OpenFoodFacts;
use App\Models\CatalogoProducto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Trae al catálogo los productos de Perú de Open Food Facts que se venden en una panadería
 * (categorías de config/catalogo.php). Los que ya están no se tocan. Respeta el límite de la
 * API (≈100 productos por minuto): una carga completa toma varios minutos.
 */
#[Signature('catalogo:openfoodfacts {--categoria=* : Solo estas categorías de Open Food Facts (ej. en:milks)} {--max=1000 : Máximo de productos nuevos}')]
#[Description('Importa productos de panadería y pastelería de Open Food Facts (Perú) al catálogo')]
class ImportarOpenFoodFacts extends Command
{
    public function handle(OpenFoodFacts $off): int
    {
        $categorias = $this->option('categoria') ?: array_keys((array) config('catalogo.categorias_panaderia'));
        $max = max(1, (int) $this->option('max'));
        $nuevos = 0;
        $vistos = [];

        foreach ($categorias as $categoria) {
            $this->line("Categoría {$categoria}…");
            for ($pagina = 1; ; $pagina++) {
                $r = $off->codigosDeCategoria((string) $categoria, $pagina);
                $pendientes = array_values(array_diff($r['codigos'], $vistos,
                    CatalogoProducto::whereIn('codigo_barras', $r['codigos'])->pluck('codigo_barras')->all()));
                $vistos = array_merge($vistos, $r['codigos']);
                foreach ($pendientes as $codigo) {
                    if ($producto = $off->importar($codigo)) {
                        $nuevos++;
                        $this->info("  + {$producto->codigo_barras} {$producto->nombre} ({$producto->categoria})");
                    }
                    if ($nuevos >= $max) {
                        break 3;
                    }
                    usleep(700_000); // límite de la API de Open Food Facts
                }
                if ($pagina >= $r['paginas']) {
                    break;
                }
            }
        }
        $this->info("Listo: {$nuevos} productos nuevos en el catálogo. Fuente: Open Food Facts (ODbL).");

        return self::SUCCESS;
    }
}
