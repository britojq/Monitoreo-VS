<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\ServiceData;
use App\Models\MonitoredService;
use Illuminate\Database\Eloquent\Collection;

interface ServiceRepositoryInterface
{
    public function findById(int $id): ?MonitoredService;

    public function findByLetter(string $letter): ?MonitoredService;

    public function getAllActive(): Collection;

    public function getAllOrdered(): Collection;

    public function create(ServiceData $data): MonitoredService;

    public function update(int $id, ServiceData $data): MonitoredService;

    public function toggleActive(int $id): MonitoredService;

    public function delete(int $id): bool;
}

