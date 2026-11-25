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
     * El local se activa con un código de un solo uso (ficha del cliente → Generar código).
     */
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
            $cliente->nuevaClave(); // se reemplaza al activar el local
            $cliente->save();
        }
        $this->command?->info('Cliente demo: "'.$cliente->nombre.'". Actívalo desde su ficha con "Generar código de activación".');
    }
}
