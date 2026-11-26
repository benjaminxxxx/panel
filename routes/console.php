<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Comprobantes que quedaron sin respuesta de SUNAT (caída de red, timeout): se reintenta con el mismo XML.
Schedule::command('facturacion:reenviar')->everyTenMinutes()->withoutOverlapping();

// Padrón reducido del RUC: SUNAT lo actualiza a diario; con una vez por semana basta para nombres y direcciones.
Schedule::command('consultas:padron')->weeklyOn(0, '03:00')->withoutOverlapping();

// Instaladores que llegaron con git (distribucion/<sistema>/version.json): se firman y publican solos.
Schedule::command('versiones:sincronizar')->everyFiveMinutes()->withoutOverlapping();
