<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\AlertData;
use App\Models\Alert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AlertRepositoryInterface
{
    public function findById(int $id): ?Alert;

    public function getActiveAlerts(): Collection;

    public function paginateFiltered(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function create(AlertData $data): Alert;

    public function acknowledge(int $id, string $userName): Alert;

    public function resolve(int $id, string $userName, ?string $notes = null): Alert;

    public function getUnresolvedCount(): int;
}
