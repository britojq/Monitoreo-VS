<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\NetworkDeviceData;
use App\Models\MonitoredNetworkDevice;
use Illuminate\Database\Eloquent\Collection;

interface NetworkDeviceRepositoryInterface
{
    public function findById(int $id): ?MonitoredNetworkDevice;

    public function findByIp(string $ip): ?MonitoredNetworkDevice;

    public function findByMac(string $mac): ?MonitoredNetworkDevice;

    public function getBySiteId(int $siteId): Collection;

    public function getAllActive(): Collection;

    public function create(NetworkDeviceData $data): MonitoredNetworkDevice;

    public function update(int $id, NetworkDeviceData $data): MonitoredNetworkDevice;

    public function delete(int $id): bool;

    public function getNextDeviceNumber(int $siteId): int;
}
