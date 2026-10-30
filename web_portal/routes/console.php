<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar ejecución automática diaria a las 02:00 AM (Fase 8)
Schedule::command('telemetry:housekeeping --force')->dailyAt('02:00');

// Programar verificación diaria de cero datos ficticios en MariaDB (Regla de Oro #6)
Schedule::command('audit:verify-zero-dummy-data')->dailyAt('03:00');

