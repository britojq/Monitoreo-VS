<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Services\System\SonarPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Process;

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

    public function triggerScanNow(): JsonResponse
    {
        $pythonScript = SonarPathService::monitorScript('monitor_web_sync.py');
        $pythonBin = SonarPathService::pythonBinary();

        if (file_exists($pythonScript) && file_exists($pythonBin)) {
            $result = Process::run("{$pythonBin} {$pythonScript} --force");
            return response()->json([
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Script de escaneo Python no encontrado.',
        ], 404);
    }
}
