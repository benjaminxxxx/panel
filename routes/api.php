<?php

use App\Http\Controllers\Api\ActualizacionController;
use App\Http\Controllers\Api\LicenciaController;
use App\Http\Middleware\AutenticarCliente;
use Illuminate\Support\Facades\Route;

// API que consume el programa del local. Contrato: docs/contrato-panel.md en restaurantev2.
Route::prefix('v1')->middleware(AutenticarCliente::class)->group(function () {
    Route::get('licencia', [LicenciaController::class, 'mostrar']);
    Route::get('actualizacion', [ActualizacionController::class, 'ultima']);
    Route::get('actualizacion/{version}/descargar', [ActualizacionController::class, 'descargar']);
});
