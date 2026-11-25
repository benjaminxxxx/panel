<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ClienteController::class, 'index'])->name('dashboard');

    Route::get('clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::post('clientes/{cliente}/bloquear', [ClienteController::class, 'bloquear'])->name('clientes.bloquear');
    Route::post('clientes/{cliente}/habilitar', [ClienteController::class, 'habilitar'])->name('clientes.habilitar');
    Route::post('clientes/{cliente}/clave', [ClienteController::class, 'regenerarClave'])->name('clientes.clave');
    Route::post('clientes/{cliente}/pagos', [PagoController::class, 'store'])->name('clientes.pagos.store');

    Route::get('versiones', [VersionController::class, 'index'])->name('versiones.index');
    Route::post('versiones', [VersionController::class, 'store'])->name('versiones.store');
    Route::post('versiones/{version}/publicar', [VersionController::class, 'publicar'])->name('versiones.publicar');
});

require __DIR__.'/settings.php';
