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

// Procesamiento asíncrono en tiempo real garantizado por systemd (ARC-08: sonar-queue-worker.service < 500ms).
// Este comando de cron actúa como salvaguarda secundaria en caso de recarga o reinicio del demonio.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();


