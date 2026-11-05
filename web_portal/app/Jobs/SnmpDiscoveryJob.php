<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\SnmpDevice;
use App\Services\System\SonarPathService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SnmpDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 2;

    public function __construct(
        public readonly int $deviceId,
        public readonly string $mode = 'poll', // 'poll' o 'interfaces'
        public readonly ?int $userId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function handle(): void
    {
        $device = SnmpDevice::find($this->deviceId);
        if (!$device) {
            Log::warning("SnmpDiscoveryJob: Dispositivo SNMP ID {$this->deviceId} no encontrado.");
            return;
        }

        $python = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('snmp_poller.py');

        if (!file_exists($python) || !file_exists($script)) {
            Log::error("SnmpDiscoveryJob: Script snmp_poller.py no encontrado.");
            return;
        }

        $argMode = $this->mode === 'interfaces' ? '--interfaces' : '--poll';

        $result = Process::run([
            $python,
            $script,
            $argMode,
            (string) $this->deviceId,
        ]);

        AuditLog::create([
            'user_id' => $this->userId,
            'user_name' => 'Sistema / Asíncrono',
            'user_email' => null,
            'user_role' => 'system',
            'event' => 'executed',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => $device->ip_address,
            'description' => "Operación SNMP asíncrona ({$this->mode}) ejecutada sobre {$device->name} ({$device->ip_address})",
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ]);

        if ($result->successful()) {
            Log::info("SnmpDiscoveryJob: Operación SNMP ({$this->mode}) completada para {$device->name}.");
        } else {
            Log::error("SnmpDiscoveryJob: Error en SNMP ({$this->mode}) para {$device->name}: " . $result->errorOutput());
        }
    }
}
