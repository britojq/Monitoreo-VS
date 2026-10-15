<?php

declare(strict_types=1);

namespace App\Actions\Snmp;

use App\Models\AuditLog;
use App\Models\SnmpDevice;
use App\Services\ClusterConfigService;
use Illuminate\Support\Facades\Auth;

readonly class CreateSnmpDeviceAction
{
    public function __construct(
        private ClusterConfigService $cluster
    ) {}

    public function execute(array $validated, ?string $ip, ?string $userAgent): array
    {
        if ($this->cluster->isSlave()) {
            return [
                'success' => false,
                'message' => "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de dispositivos SNMP deben realizarse en el servidor MASTER ({$this->cluster->getMasterApiUrl()}).",
                'device' => null,
            ];
        }

        $device = new SnmpDevice();
        $device->name = $validated['name'];
        $device->ip_address = $validated['ip_address'];
        $device->snmp_version = $validated['snmp_version'];
        if (!empty($validated['community'])) {
            $device->setCommunity($validated['community']);
        }
        $device->device_type = $validated['device_type'];
        $device->site_id = $validated['site_id'] ?? null;
        $device->poll_interval_seconds = $validated['poll_interval_seconds'] ?? 60;
        $device->notes = $validated['notes'] ?? null;
        $device->is_active = true;
        $device->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email ?? 'system@local',
            'user_role' => Auth::user()?->role ?? 'admin',
            'event' => 'created',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => "Dispositivo SNMP ({$device->ip_address})",
            'description' => "Dispositivo SNMP registrado manualmente: {$device->name} ({$device->ip_address})",
            'new_values' => $device->toArray(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        $cmd = "/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/snmp_poller.py --interfaces {$device->id}";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B {$cmd}", 'r'));
        } else {
            exec("{$cmd} > /dev/null 2>&1 &");
        }

        return [
            'success' => true,
            'message' => "Dispositivo {$device->name} registrado exitosamente. Se inició el sondeo inicial en segundo plano.",
            'device' => $device,
        ];
    }
}
