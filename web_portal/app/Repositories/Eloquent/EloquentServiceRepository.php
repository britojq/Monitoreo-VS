<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ServiceRepositoryInterface;
use App\DTOs\ServiceData;
use App\Models\MonitoredService;
use Illuminate\Database\Eloquent\Collection;

class EloquentServiceRepository implements ServiceRepositoryInterface
{
    public function findById(int $id): ?MonitoredService
    {
        return MonitoredService::find($id);
    }

    public function findByLetter(string $letter): ?MonitoredService
    {
        return MonitoredService::where('letter', strtoupper($letter))->first();
    }

    public function getAllActive(): Collection
    {
        return MonitoredService::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('letter')
            ->get();
    }

    public function getAllOrdered(): Collection
    {
        return MonitoredService::orderBy('sort_order')->get();
    }

    public function create(ServiceData $data): MonitoredService
    {
        return MonitoredService::create($data->toArray());
    }

    public function update(int $id, ServiceData $data): MonitoredService
    {
        $service = MonitoredService::findOrFail($id);
        $service->update(array_filter($data->toArray(), fn ($v) => $v !== null));
        return $service->fresh();
    }

    public function toggleActive(int $id): MonitoredService
    {
        $service = MonitoredService::findOrFail($id);
        $service->is_active = !$service->is_active;
        $service->save();
        return $service->fresh();
    }

    public function delete(int $id): bool
    {
        $service = MonitoredService::find($id);
        if (!$service) {
            return false;
        }
        return (bool) $service->delete();
    }
}
