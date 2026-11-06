<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\ProxyData;
use App\Models\MonitoredProxy;
use Illuminate\Database\Eloquent\Collection;

interface ProxyRepositoryInterface
{
    public function findById(int $id): ?MonitoredProxy;

    public function findByLetter(string $letter): ?MonitoredProxy;

    public function getAllOrdered(): Collection;

    public function create(ProxyData $data): MonitoredProxy;

    public function update(int $id, ProxyData $data): MonitoredProxy;

    public function toggleActive(int $id): MonitoredProxy;

    public function delete(int $id): bool;
}
