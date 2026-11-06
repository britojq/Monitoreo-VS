<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\System\SonarPathService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TriggerTelemetrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function handle(): void
    {
        $python = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('monitor_web_sync.py');

        if (!file_exists($python) || !file_exists($script)) {
            Log::error("TriggerTelemetrySyncJob: Script monitor_web_sync.py o Python no encontrado.");
            return;
        }

        $result = Process::run([
            $python,
            $script,
            '--force',
        ]);

        AuditLog::create([
            'user_id' => $this->userId,
            'user_name' => 'Sistema / Asíncrono',
            'user_email' => null,
            'user_role' => 'system',
            'event' => 'executed',
            'module' => 'sync',
            'auditable_type' => null,
            'auditable_id' => null,
            'entity_name' => 'Escaneo de Telemetría',
            'entity_label' => 'monitor_web_sync.py',
            'description' => 'Ejecución asíncrona de escaneo completo forzado de telemetría.',
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ]);

        if ($result->successful()) {
            Log::info("TriggerTelemetrySyncJob: Escaneo de telemetría completado exitosamente.");
        } else {
            Log::error("TriggerTelemetrySyncJob: Fallo en escaneo de telemetría: " . $result->errorOutput());
        }
    }
}
