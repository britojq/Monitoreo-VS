<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\InventoryAssetData;
use App\Models\InventoryAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventoryAssetRepositoryInterface
{
    public function findById(int $id): ?InventoryAsset;

    public function findByAssetTag(string $tag): ?InventoryAsset;

    public function findByIp(string $ip): ?InventoryAsset;

    public function findByMac(string $mac): ?InventoryAsset;

    public function getAllActive(): Collection;

    public function paginateFiltered(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function create(InventoryAssetData $data): InventoryAsset;

    public function update(int $id, InventoryAssetData $data): InventoryAsset;

    public function delete(int $id): bool;

    public function getSummaryStats(): array;
}
