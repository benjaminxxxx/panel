<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos iniciales para desarrollo. Idempotente: se puede ejecutar varias veces.
     * La clave del cliente demo coincide con la que compilar.ps1 pone en el instalador
     * de prueba; en producción cada cliente recibe una clave aleatoria desde el panel.
     */
    public const CLAVE_DEMO = 'rst_demo_local_2026';

    public function run(): void
    {
        User::updateOrCreate(['email' => 'benjamin_unitek@hotmail.com'], [
            'name' => 'Benjamin',
            'password' => Hash::make('12345678'),
            'email_verified_at' => now(),
        ]);

        $cliente = Cliente::firstOrNew(['codigo' => 'restaurante-demo']);
        if (! $cliente->exists) {
            $cliente->fill([
                'nombre' => 'Restaurante demo',
                'contacto' => 'Local de prueba',
                'vence_el' => now()->addMonth()->toDateString(),
            ]);
            $cliente->nuevaClave(self::CLAVE_DEMO);
            $cliente->save();
        }
        $this->command?->info('Cliente demo: "'.$cliente->nombre.'" · clave '.self::CLAVE_DEMO);
    }
}
