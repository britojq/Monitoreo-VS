<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SiteRepositoryInterface;
use App\DTOs\SiteData;
use App\Models\MonitoredSite;
use Illuminate\Database\Eloquent\Collection;

class EloquentSiteRepository implements SiteRepositoryInterface
{
    public function findById(int $id): ?MonitoredSite
    {
        return MonitoredSite::with(['devices', 'networkDevices'])->find($id);
    }

    public function findByLetter(string $letter): ?MonitoredSite
    {
        return MonitoredSite::with(['devices', 'networkDevices'])
            ->where('letter', strtoupper($letter))
            ->first();
    }

    public function getAllActive(): Collection
    {
        return MonitoredSite::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('letter')
            ->get();
    }

    public function getAllWithDevices(): Collection
    {
        return MonitoredSite::with(['devices', 'networkDevices'])
            ->orderBy('sort_order')
            ->orderBy('letter')
            ->get();
    }

    public function create(SiteData $data): MonitoredSite
    {
        return MonitoredSite::create($data->toArray());
    }

    public function update(int $id, SiteData $data): MonitoredSite
    {
        $site = MonitoredSite::findOrFail($id);
        $site->update(array_filter($data->toArray(), fn ($v) => $v !== null));
        return $site->fresh(['devices', 'networkDevices']);
    }

    public function delete(int $id): bool
    {
        $site = MonitoredSite::find($id);
        if (!$site) {
            return false;
        }
        return (bool) $site->delete();
    }
}
