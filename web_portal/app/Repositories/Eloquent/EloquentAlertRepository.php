<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AlertRepositoryInterface;
use App\DTOs\AlertData;
use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentAlertRepository implements AlertRepositoryInterface
{
    public function findById(int $id): ?Alert
    {
        return Alert::with(['rule'])->find($id);
    }

    public function getActiveAlerts(): Collection
    {
        return Alert::with(['rule'])
            ->whereIn('status', ['active', 'acknowledged', 'escalated'])
            ->orderBy('fired_at', 'desc')
            ->get();
    }

    public function paginateFiltered(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Alert::with(['rule']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('entity_name', 'like', $search)
                  ->orWhere('message', 'like', $search);
            });
        }

        return $query->orderBy('fired_at', 'desc')->paginate($perPage);
    }

    public function create(AlertData $data): Alert
    {
        return Alert::create($data->toArray());
    }

    public function acknowledge(int $id, string $userName): Alert
    {
        $alert = Alert::findOrFail($id);
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => Carbon::now(),
            'acknowledged_by' => $userName,
        ]);
        return $alert->fresh(['rule']);
    }

    public function resolve(int $id, string $userName, ?string $notes = null): Alert
    {
        $alert = Alert::findOrFail($id);
        $firedAt = $alert->fired_at ?? Carbon::now();
        $durationSeconds = (int) Carbon::now()->diffInSeconds($firedAt);

        $payload = [
            'status' => 'resolved',
            'resolved_at' => Carbon::now(),
            'resolved_by' => $userName,
            'duration_seconds' => $durationSeconds,
        ];

        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        $alert->update($payload);
        return $alert->fresh(['rule']);
    }

    public function getUnresolvedCount(): int
    {
        return Alert::whereIn('status', ['active', 'acknowledged', 'escalated'])->count();
    }
}
