<?php

use App\Http\Controllers\Api\ActivacionController;
use App\Http\Controllers\Api\ActualizacionController;
use App\Http\Controllers\Api\LicenciaController;
use App\Http\Controllers\Api\PublicacionController;
use App\Http\Middleware\AutenticarCliente;
use Illuminate\Support\Facades\Route;

// API que consume el programa del local. Contrato: docs/contrato-panel.md en restaurantev2.
Route::prefix('v1')->group(function () {
    Route::post('activar', [ActivacionController::class, 'activar'])->middleware('throttle:10,1');
    Route::post('versiones', [PublicacionController::class, 'store']); // GitHub Actions, con token

    Route::middleware(AutenticarCliente::class)->group(function () {
        Route::get('licencia', [LicenciaController::class, 'mostrar']);
        Route::get('actualizacion', [ActualizacionController::class, 'ultima']);
        Route::get('actualizacion/{version}/descargar', [ActualizacionController::class, 'descargar']);
    });
});
