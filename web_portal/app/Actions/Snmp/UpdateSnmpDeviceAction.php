<?php

declare(strict_types=1);

namespace App\Actions\Snmp;

use App\Models\AuditLog;
use App\Models\SnmpDevice;
use App\Services\ClusterConfigService;
use Illuminate\Support\Facades\Auth;

readonly class UpdateSnmpDeviceAction
{
    public function __construct(
        private ClusterConfigService $cluster
    ) {}

    public function execute(int $id, array $validated, bool $isActive, ?string $ip, ?string $userAgent): array
    {
        if ($this->cluster->isSlave()) {
            return [
                'success' => false,
                'message' => "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de dispositivos SNMP deben realizarse en el servidor MASTER ({$this->cluster->getMasterApiUrl()}).",
                'device' => null,
            ];
        }

        $device = SnmpDevice::findOrFail($id);
        $oldValues = $device->toArray();

        $device->name = $validated['name'];
        $device->ip_address = $validated['ip_address'];
        if (!empty($validated['snmp_port'])) {
            $device->snmp_port = (int) $validated['snmp_port'];
        }
        $device->snmp_version = $validated['snmp_version'];
        if (!empty($validated['community'])) {
            $device->setCommunity($validated['community']);
        }
        $device->device_type = $validated['device_type'];
        $device->site_id = $validated['site_id'] ?? null;
        $device->poll_interval_seconds = $validated['poll_interval_seconds'] ?? 60;
        $device->is_active = $isActive;
        $device->notes = $validated['notes'] ?? null;
        $device->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email ?? 'system@local',
            'user_role' => Auth::user()?->role ?? 'admin',
            'event' => 'updated',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => "Dispositivo SNMP ({$device->ip_address})",
            'description' => "Dispositivo SNMP actualizado: {$device->name}",
            'old_values' => $oldValues,
            'new_values' => $device->toArray(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [
            'success' => true,
            'message' => "Dispositivo {$device->name} actualizado correctamente.",
            'device' => $device,
        ];
    }
}
