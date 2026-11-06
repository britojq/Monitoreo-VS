<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TriggerTelemetrySyncJob;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Services\System\SonarPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    public function exportToConfigFiles(): bool
    {
        // MariaDB es la única fuente de verdad centralizada.
        // Todo el ecosistema (Laravel, Python, Bot) opera directamente sobre MariaDB.
        // No se generan ni se depende de archivos planos .conf.
        return true;
    }

    public function triggerSyncManual(): RedirectResponse
    {
        return back()->with('success', 'Configuración de monitoreo activa y sincronizada directamente en la base de datos central.');
    }

    /**
     * Dispara escaneo de telemetría asíncrono desacoplado (HTTP 202 Accepted).
     */
    public function triggerScanNow(): JsonResponse
    {
        TriggerTelemetrySyncJob::dispatch(
            Auth::id() ? (int) Auth::id() : null,
            request()->ip(),
            request()->userAgent()
        );

        return response()->json([
            'success' => true,
            'status' => 'queued',
            'message' => '⚡ Escaneo completo de telemetría encolado exitosamente para ejecución en segundo plano.',
            'output' => 'Tarea asíncrona despachada al worker (HTTP 202 Accepted).',
        ], 202);
    }
}
