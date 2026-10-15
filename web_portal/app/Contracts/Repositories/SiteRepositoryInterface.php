<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\SiteData;
use App\Models\MonitoredSite;
use Illuminate\Database\Eloquent\Collection;

interface SiteRepositoryInterface
{
    public function findById(int $id): ?MonitoredSite;

    public function findByLetter(string $letter): ?MonitoredSite;

    public function getAllActive(): Collection;

    public function getAllWithDevices(): Collection;

    public function create(SiteData $data): MonitoredSite;

    public function update(int $id, SiteData $data): MonitoredSite;

    public function delete(int $id): bool;
}
