<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComprobantePublicoController;
use App\Http\Controllers\DescargaController;
use App\Http\Controllers\EmpresaArchivoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\VersionController;
use App\Livewire\Empresas\Formulario;
use App\Livewire\Empresas\Show;
use Illuminate\Support\Facades\Route;

// Sin sesión, "auth" manda al login; con sesión, al panel.
Route::redirect('/', 'dashboard')->middleware('auth')->name('home');

// Públicas: lo que abre el QR del restaurante en el celular (abrir la app o instalarla).
Route::get('app', [AppController::class, 'abrir'])->name('app');
Route::get('app/android/descargar', [AppController::class, 'descargar'])->name('app.android');

// Enlace firmado al PDF A4 para el cliente final (lo comparten los sistemas por WhatsApp).
Route::get('c/{comprobante}', ComprobantePublicoController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('comprobantes.publico');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Facturación electrónica: cada usuario administra sus empresas.
    Route::redirect('empresas', 'dashboard')->name('empresas.index');
    Route::livewire('empresas/registrar', Formulario::class)->name('empresas.create');
    Route::livewire('empresas/{empresa:uuid}', Show::class)->name('empresas.show');
    Route::livewire('empresas/{empresa:uuid}/editar', Formulario::class)->name('empresas.edit');
    Route::get('empresas/{empresa:uuid}/comprobantes/{comprobante:uuid}/{archivo}', EmpresaArchivoController::class)
        ->whereIn('archivo', ['xml', 'cdr', 'a4', 'ticket'])
        ->name('empresas.comprobantes.archivo');

    // Proveedor: locales que alquilan los sistemas, versiones, catálogo e instaladores.
    Route::middleware('can:proveedor')->group(function () {
        Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
        Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
        Route::post('clientes/{cliente}/bloquear', [ClienteController::class, 'bloquear'])->name('clientes.bloquear');
        Route::post('clientes/{cliente}/habilitar', [ClienteController::class, 'habilitar'])->name('clientes.habilitar');
        Route::post('clientes/{cliente}/clave', [ClienteController::class, 'regenerarClave'])->name('clientes.clave');
        Route::post('clientes/{cliente}/activacion', [ClienteController::class, 'activacion'])->name('clientes.activacion');
        Route::post('clientes/{cliente}/reinicio', [ClienteController::class, 'permitirReinicio'])->name('clientes.reinicio');
        Route::post('clientes/{cliente}/pagos', [PagoController::class, 'store'])->name('clientes.pagos.store');

        Route::get('descargas', [DescargaController::class, 'index'])->name('descargas');
        Route::get('descargas/windows/{aplicacion?}', [DescargaController::class, 'windows'])->name('descargas.windows');

        Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
        Route::get('catalogo/nuevo', [CatalogoController::class, 'create'])->name('catalogo.create');
        Route::post('catalogo', [CatalogoController::class, 'store'])->name('catalogo.store');
        Route::post('catalogo/importar', [CatalogoController::class, 'importar'])->name('catalogo.importar');
        Route::get('catalogo/{producto}', [CatalogoController::class, 'edit'])->name('catalogo.edit');
        Route::put('catalogo/{producto}', [CatalogoController::class, 'update'])->name('catalogo.update');

        Route::get('versiones', [VersionController::class, 'index'])->name('versiones.index');
        Route::post('versiones', [VersionController::class, 'store'])->name('versiones.store');
        Route::post('versiones/{version}/publicar', [VersionController::class, 'publicar'])->name('versiones.publicar');
    });
});

require __DIR__.'/settings.php';
