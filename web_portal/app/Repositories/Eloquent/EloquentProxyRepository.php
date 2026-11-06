<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProxyRepositoryInterface;
use App\DTOs\ProxyData;
use App\Models\MonitoredProxy;
use Illuminate\Database\Eloquent\Collection;

class EloquentProxyRepository implements ProxyRepositoryInterface
{
    public function findById(int $id): ?MonitoredProxy
    {
        return MonitoredProxy::find($id);
    }

    public function findByLetter(string $letter): ?MonitoredProxy
    {
        return MonitoredProxy::where('letter', strtoupper($letter))->first();
    }

    public function getAllOrdered(): Collection
    {
        return MonitoredProxy::orderBy('id')->get();
    }

    public function create(ProxyData $data): MonitoredProxy
    {
        return MonitoredProxy::create($data->toArray());
    }

    public function update(int $id, ProxyData $data): MonitoredProxy
    {
        $proxy = MonitoredProxy::findOrFail($id);
        $payload = $data->toArray();
        $proxy->update($payload);
        return $proxy->fresh();
    }

    public function toggleActive(int $id): MonitoredProxy
    {
        $proxy = MonitoredProxy::findOrFail($id);
        $proxy->is_active = !$proxy->is_active;
        $proxy->save();
        return $proxy->fresh();
    }

    public function delete(int $id): bool
    {
        $proxy = MonitoredProxy::find($id);
        if (!$proxy) {
            return false;
        }
        return (bool) $proxy->delete();
    }
}
