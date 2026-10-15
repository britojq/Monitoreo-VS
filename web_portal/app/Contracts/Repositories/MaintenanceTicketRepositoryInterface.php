<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\MaintenanceTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MaintenanceTicketRepositoryInterface
{
    public function findById(int $id): ?MaintenanceTicket;

    public function findByCode(string $code): ?MaintenanceTicket;

    public function paginateFiltered(?string $status, ?string $type, ?string $priority, ?string $search, int $perPage = 15): LengthAwarePaginator;

    public function getStats(): array;

    public function create(array $data): MaintenanceTicket;

    public function update(int $id, array $data): MaintenanceTicket;

    public function delete(int $id): bool;
}
