<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar ejecución automática diaria a las 02:00 AM (Fase 8)
Schedule::command('telemetry:housekeeping --force')->dailyAt('02:00');
