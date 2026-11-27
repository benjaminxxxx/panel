<?php

namespace App\Console\Commands;

use App\Licencias\Firmador;
use App\Models\Version;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Vuelve a firmar todas las versiones con la llave actual del panel. Se usa tras cambiar
 * LICENCIA_LLAVE_PRIVADA: las firmas guardadas con la llave anterior las rechazan los locales.
 */
#[Signature('versiones:refirmar')]
#[Description('Firma de nuevo todas las versiones con la llave actual (tras cambiar LICENCIA_LLAVE_PRIVADA)')]
class RefirmarVersiones extends Command
{
    public function handle(Firmador $firmador): int
    {
        $total = 0;
        foreach (Version::all() as $v) {
            $v->forceFill(['firma' => $firmador->firmar(Firmador::mensajeVersion($v->version, $v->sha256, $v->aplicacion))])->save();
            $total++;
        }
        $this->info("{$total} versiones firmadas de nuevo.");

        return self::SUCCESS;
    }
}
