<?php

declare(strict_types=1);

namespace App\Actions\Snmp;

use App\Models\AuditLog;
use App\Models\SnmpDevice;
use App\Services\ClusterConfigService;
use Illuminate\Support\Facades\Auth;

readonly class DeleteSnmpDeviceAction
{
    public function __construct(
        private ClusterConfigService $cluster
    ) {}

    public function execute(int $id, ?string $ip, ?string $userAgent): array
    {
        if ($this->cluster->isSlave()) {
            return [
                'success' => false,
                'message' => "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de dispositivos SNMP deben realizarse en el servidor MASTER ({$this->cluster->getMasterApiUrl()}).",
            ];
        }

        $device = SnmpDevice::findOrFail($id);
        $name = $device->name;
        $ipAddr = $device->ip_address;
        $oldValues = $device->toArray();

        $device->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email ?? 'system@local',
            'user_role' => Auth::user()?->role ?? 'admin',
            'event' => 'deleted',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $id,
            'entity_name' => $name,
            'entity_label' => "Dispositivo SNMP ({$ipAddr})",
            'description' => "Dispositivo SNMP eliminado del sistema: {$name} ({$ipAddr})",
            'old_values' => $oldValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [
            'success' => true,
            'message' => "Dispositivo {$name} eliminado.",
        ];
    }
}
