<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\NetworkDeviceRepositoryInterface;
use App\DTOs\NetworkDeviceData;
use App\Models\MonitoredNetworkDevice;
use Illuminate\Database\Eloquent\Collection;

class EloquentNetworkDeviceRepository implements NetworkDeviceRepositoryInterface
{
    public function findById(int $id): ?MonitoredNetworkDevice
    {
        return MonitoredNetworkDevice::with(['site'])->find($id);
    }

    public function findByIp(string $ip): ?MonitoredNetworkDevice
    {
        return MonitoredNetworkDevice::with(['site'])
            ->where('ip', $ip)
            ->first();
    }

    public function findByMac(string $mac): ?MonitoredNetworkDevice
    {
        return MonitoredNetworkDevice::with(['site'])
            ->where('mac', $mac)
            ->first();
    }

    public function getBySiteId(int $siteId): Collection
    {
        return MonitoredNetworkDevice::where('monitored_site_id', $siteId)
            ->orderBy('device_number')
            ->get();
    }

    public function getAllActive(): Collection
    {
        return MonitoredNetworkDevice::with(['site'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function create(NetworkDeviceData $data): MonitoredNetworkDevice
    {
        $payload = array_filter($data->toArray(), fn ($v) => $v !== null);
        if ($data->sshPassword) {
            $payload['ssh_password_encrypted'] = $data->sshPassword;
        }
        if ($data->sshEnableSecret) {
            $payload['ssh_enable_secret_encrypted'] = $data->sshEnableSecret;
        }
        if ($data->vncPassword) {
            $payload['vnc_password_encrypted'] = $data->vncPassword;
        }

        return MonitoredNetworkDevice::create($payload);
    }

    public function update(int $id, NetworkDeviceData $data): MonitoredNetworkDevice
    {
        $device = MonitoredNetworkDevice::findOrFail($id);
        $payload = array_filter($data->toArray(), fn ($v) => $v !== null);

        if ($data->sshPassword) {
            $payload['ssh_password_encrypted'] = $data->sshPassword;
        }
        if ($data->sshEnableSecret) {
            $payload['ssh_enable_secret_encrypted'] = $data->sshEnableSecret;
        }
        if ($data->vncPassword) {
            $payload['vnc_password_encrypted'] = $data->vncPassword;
        }

        $device->update($payload);
        return $device->fresh(['site']);
    }

    public function delete(int $id): bool
    {
        $device = MonitoredNetworkDevice::find($id);
        if (!$device) {
            return false;
        }
        return (bool) $device->delete();
    }

    public function getNextDeviceNumber(int $siteId): int
    {
        $max = MonitoredNetworkDevice::where('monitored_site_id', $siteId)->max('device_number');
        return ($max ? (int) $max : 0) + 1;
    }
}
