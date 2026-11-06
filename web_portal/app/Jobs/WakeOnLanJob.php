<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\WolDevice;
use App\Services\System\SonarPathService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WakeOnLanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(
        public readonly int $deviceId,
        public readonly ?int $userId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function handle(): void
    {
        $device = WolDevice::find($this->deviceId);
        if (!$device) {
            Log::warning("WakeOnLanJob: Dispositivo ID {$this->deviceId} no encontrado.");
            return;
        }

        $python = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('wol_sender.py');

        if (!file_exists($python) || !file_exists($script)) {
            Log::error("WakeOnLanJob: Binario Python o script wol_sender.py no encontrado.");
            return;
        }

        $result = Process::run([
            $python,
            $script,
            '--wake',
            (string) $this->deviceId,
            '--json',
        ]);

        $output = $result->output();
        $data = json_decode($output, true);

        AuditLog::create([
            'user_id' => $this->userId,
            'user_name' => 'Sistema / Asíncrono',
            'user_email' => null,
            'user_role' => 'system',
            'event' => 'executed',
            'module' => 'wol',
            'auditable_type' => WolDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => $device->mac_address,
            'description' => "Magic Packet Wake-on-LAN transmitido asíncronamente hacia {$device->name} ({$device->mac_address})",
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ]);

        if (is_array($data) && ($data['status'] ?? '') === 'ok') {
            $device->update(['last_woken_at' => now()]);
            Log::info("WakeOnLanJob: Magic Packet transmitido con éxito a {$device->name} ({$device->mac_address}).");
        } else {
            Log::warning("WakeOnLanJob: Error al transmitir Magic Packet a {$device->name}: " . ($data['message'] ?? $result->errorOutput()));
        }
    }
}
