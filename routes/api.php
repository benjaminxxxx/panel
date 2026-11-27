<?php

use App\Http\Controllers\Api\ActivacionController;
use App\Http\Controllers\Api\ActualizacionController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\ConsultaController;
use App\Http\Controllers\Api\LicenciaController;
use App\Http\Controllers\Api\PublicacionController;
use App\Http\Controllers\Api\V1\ComprobanteController;
use App\Http\Controllers\Api\V1\SerieController;
use App\Http\Controllers\AppController;
use App\Http\Middleware\AutenticarCliente;
use App\Http\Middleware\AutenticarConsulta;
use Illuminate\Support\Facades\Route;

// API de los programas de los locales (restaurante, panadería). Contrato: docs/contrato-panel.md de cada sistema.
Route::prefix('v1')->group(function () {
    Route::post('activar', [ActivacionController::class, 'activar'])->middleware('throttle:10,1');
    Route::post('versiones', [PublicacionController::class, 'store']); // GitHub Actions, con token
    Route::get('android', [AppController::class, 'ultima']); // la app Android pregunta si hay versión nueva

    // Catálogo público de productos con código de barras (lo consultan las panaderías).
    Route::get('catalogo', [CatalogoController::class, 'buscar'])->middleware('throttle:60,1');
    Route::get('catalogo/codigo/{codigo}', [CatalogoController::class, 'porCodigo'])->middleware('throttle:30,1');
    Route::get('catalogo/{producto}/foto', [CatalogoController::class, 'foto'])->name('api.catalogo.foto');

    Route::middleware(AutenticarCliente::class)->group(function () {
        Route::get('licencia', [LicenciaController::class, 'mostrar']);
        Route::get('actualizacion', [ActualizacionController::class, 'ultima']);
        Route::get('actualizacion/{version}/descargar', [ActualizacionController::class, 'descargar']);
    });

    // Consulta de RUC y DNI: con la clave de un local, el token de una empresa o el token de consultas.
    Route::middleware([AutenticarConsulta::class, 'throttle:consultas'])->name('api.consultas.')->group(function () {
        Route::get('consultas/ruc/{ruc}', [ConsultaController::class, 'ruc'])->name('ruc');
        Route::get('consultas/dni/{dni}', [ConsultaController::class, 'dni'])->name('dni');
    });
});

// API de facturación electrónica (token de empresa fk_...). Contrato: docs/api-facturacion.md.
Route::prefix('v1')
    ->middleware(['sistema.token', 'throttle:facturacion'])
    ->name('api.')
    ->group(function () {
        Route::get('yo', [ComprobanteController::class, 'yo'])->name('yo');
        Route::put('series/{serie}', [SerieController::class, 'update'])->name('series.update');

        Route::post('comprobantes', [ComprobanteController::class, 'store'])->name('comprobantes.store');
        Route::get('comprobantes/{uuid}', [ComprobanteController::class, 'show'])->name('comprobantes.show');
        Route::post('comprobantes/{uuid}/reenviar', [ComprobanteController::class, 'reenviar'])->name('comprobantes.reenviar');
        Route::post('comprobantes/{uuid}/nota-credito', [ComprobanteController::class, 'notaCredito'])->name('comprobantes.nota-credito');
        Route::post('comprobantes/{uuid}/nota-debito', [ComprobanteController::class, 'notaDebito'])->name('comprobantes.nota-debito');
        Route::get('comprobantes/{uuid}/xml', [ComprobanteController::class, 'xml'])->name('comprobantes.xml');
        Route::get('comprobantes/{uuid}/cdr', [ComprobanteController::class, 'cdr'])->name('comprobantes.cdr');
        Route::get('comprobantes/{uuid}/pdf', [ComprobanteController::class, 'pdf'])->name('comprobantes.pdf');
    });
