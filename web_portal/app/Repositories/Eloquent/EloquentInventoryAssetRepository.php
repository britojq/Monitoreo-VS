<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\DTOs\InventoryAssetData;
use App\Models\InventoryAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentInventoryAssetRepository implements InventoryAssetRepositoryInterface
{
    public function findById(int $id): ?InventoryAsset
    {
        return InventoryAsset::with(['site', 'networkDevice', 'peripherals', 'networkInterfaces'])->find($id);
    }

    public function findByAssetTag(string $tag): ?InventoryAsset
    {
        return InventoryAsset::with(['site', 'networkDevice', 'peripherals', 'networkInterfaces'])
            ->where('asset_tag', $tag)
            ->first();
    }

    public function findByIp(string $ip): ?InventoryAsset
    {
        return InventoryAsset::with(['site', 'networkDevice', 'peripherals', 'networkInterfaces'])
            ->where('ip_address', $ip)
            ->first();
    }

    public function findByMac(string $mac): ?InventoryAsset
    {
        return InventoryAsset::with(['site', 'networkDevice', 'peripherals', 'networkInterfaces'])
            ->where('mac_address', $mac)
            ->first();
    }

    public function getAllActive(): Collection
    {
        return InventoryAsset::with(['site', 'networkDevice'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function paginateFiltered(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = InventoryAsset::with(['site', 'networkDevice']);

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('asset_tag', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhere('mac_address', 'like', $search)
                  ->orWhere('serial_number', 'like', $search)
                  ->orWhere('custodian_name', 'like', $search);
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['monitored_site_id'])) {
            $query->where('monitored_site_id', (int) $filters['monitored_site_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(InventoryAssetData $data): InventoryAsset
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

        return InventoryAsset::create($payload);
    }

    public function update(int $id, InventoryAssetData $data): InventoryAsset
    {
        $asset = InventoryAsset::findOrFail($id);
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

        $asset->update($payload);
        return $asset->fresh(['site', 'networkDevice', 'peripherals', 'networkInterfaces']);
    }

    public function delete(int $id): bool
    {
        $asset = InventoryAsset::find($id);
        if (!$asset) {
            return false;
        }
        return (bool) $asset->delete();
    }

    public function getSummaryStats(): array
    {
        return [
            'total' => InventoryAsset::count(),
            'active' => InventoryAsset::where('is_active', true)->count(),
            'in_use' => InventoryAsset::where('status', 'in_use')->count(),
            'in_stock' => InventoryAsset::where('status', 'in_stock')->count(),
            'in_repair' => InventoryAsset::where('status', 'in_repair')->count(),
            'decommissioned' => InventoryAsset::where('status', 'decommissioned')->count(),
        ];
    }
}
