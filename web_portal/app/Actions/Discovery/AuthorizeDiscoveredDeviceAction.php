<?php

declare(strict_types=1);

namespace App\Actions\Discovery;

use App\Models\DiscoveredDevice;
use App\Models\DiscoveredDeviceHistory;
use App\Models\MonitoredSite;
use App\Models\User;
use App\Services\System\SonarPathService;

class AuthorizeDiscoveredDeviceAction
{
    public function authorize(int $id, ?User $user, string $deviceType, ?string $notes): DiscoveredDevice
    {
        $device = DiscoveredDevice::findOrFail($id);
        $device->authorizeDevice($user?->id, $deviceType, $notes);
        return $device;
    }

    public function markRogue(int $id, ?User $user, string $reason): DiscoveredDevice
    {
        $device = DiscoveredDevice::findOrFail($id);
        $device->markAsRogue($user?->id, $reason);
        return $device;
    }

    public function update(int $id, array $validated, ?User $user, bool $hasAuthorized): DiscoveredDevice
    {
        $device = DiscoveredDevice::findOrFail($id);

        $prevStatus = $device->classification_status;
        $prevSiteId = $device->site_id;
        $newSiteId = array_key_exists('site_id', $validated) ? $validated['site_id'] : $device->site_id;

        $device->update([
            'device_type' => $validated['device_type'],
            'classification_status' => $validated['classification_status'],
            'is_authorized' => $hasAuthorized,
            'hostname' => $validated['hostname'] ?? $device->hostname,
            'site_id' => $newSiteId,
            'notes' => $validated['notes'] ?? $device->notes,
            'classified_by' => $user?->id,
            'classified_at' => now(),
        ]);

        if ($prevStatus !== $validated['classification_status']) {
            DiscoveredDeviceHistory::create([
                'discovered_device_id' => $device->id,
                'event_type' => 'classified',
                'previous_value' => $prevStatus,
                'new_value' => $validated['classification_status'],
                'occurred_at' => now(),
            ]);
        }

        if ((int)$prevSiteId !== (int)$newSiteId) {
            $prevSiteName = $prevSiteId ? (MonitoredSite::find($prevSiteId)?->name ?? "Sede #{$prevSiteId}") : 'Sin Asignar';
            $newSiteName = $newSiteId ? (MonitoredSite::find($newSiteId)?->name ?? "Sede #{$newSiteId}") : 'Sin Asignar';
            DiscoveredDeviceHistory::create([
                'discovered_device_id' => $device->id,
                'event_type' => 'classified',
                'previous_value' => "Sede previa: {$prevSiteName}",
                'new_value' => "Sede asignada: {$newSiteName}",
                'occurred_at' => now(),
            ]);
        }

        return $device;
    }

    public function scanNow(?string $subnet = null): void
    {
        $pythonBin = SonarPathService::pythonBinary();
        $scriptPath = SonarPathService::monitorScript('network_discovery.py');

        $cmd = [$pythonBin, $scriptPath, '--manual'];
        if (!empty($subnet)) {
            $cmd[] = '--subnet';
            $cmd[] = $subnet;
        } else {
            $cmd[] = '--all';
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/tmp/monitor/network_discovery.log', 'a'],
            2 => ['file', '/tmp/monitor/network_discovery.log', 'a'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            proc_close($process);
        }
    }
}
