<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\PredictiveAnomaly;
use App\Services\System\SonarPathService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AnalyzeNetworkPredictiveJob implements ShouldQueue
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
        $script = SonarPathService::monitorScript('predictive_analyzer.py');

        if (!file_exists($python) || !file_exists($script)) {
            Log::error("AnalyzeNetworkPredictiveJob: Script predictive_analyzer.py no encontrado.");
            return;
        }

        $result = Process::run([
            $python,
            $script,
            '--analyze',
            '--json',
        ]);

        AuditLog::create([
            'user_id' => $this->userId,
            'user_name' => 'Sistema / Asíncrono',
            'user_email' => null,
            'user_role' => 'system',
            'event' => 'executed',
            'module' => 'predictive',
            'auditable_type' => PredictiveAnomaly::class,
            'auditable_id' => null,
            'entity_name' => 'Análisis Predictivo',
            'entity_label' => 'Motor Local de IA',
            'description' => 'Ejecución asíncrona del motor local de IA y análisis predictivo de telemetría.',
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ]);

        if ($result->successful()) {
            Log::info("AnalyzeNetworkPredictiveJob: Ciclo de análisis predictivo completado con éxito.");
        } else {
            Log::error("AnalyzeNetworkPredictiveJob: Error en análisis predictivo: " . $result->errorOutput());
        }
    }
}
