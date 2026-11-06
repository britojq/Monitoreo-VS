<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MaintenanceTicketRepositoryInterface;
use App\Models\MaintenanceTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentMaintenanceTicketRepository implements MaintenanceTicketRepositoryInterface
{
    public function findById(int $id): ?MaintenanceTicket
    {
        return MaintenanceTicket::find($id);
    }

    public function findByCode(string $code): ?MaintenanceTicket
    {
        return MaintenanceTicket::where('ticket_code', $code)->first();
    }

    public function paginateFiltered(?string $status, ?string $type, ?string $priority, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        $query = MaintenanceTicket::with(['asset.monitoredSite', 'technician'])
            ->orderBy('id', 'desc');

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($type)) {
            $query->where('type', $type);
        }

        if (!empty($priority)) {
            $query->where('priority', $priority);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('asset', function ($qa) use ($search) {
                      $qa->where('name', 'like', "%{$search}%")
                         ->orWhere('serial_number', 'like', "%{$search}%")
                         ->orWhere('asset_tag', 'like', "%{$search}%");
                  })
                  ->orWhereHas('technician', function ($qt) use ($search) {
                      $qt->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->paginate($perPage);
    }

    public function getStats(): array
    {
        return [
            'scheduled' => MaintenanceTicket::where('status', 'scheduled')->count(),
            'in_progress' => MaintenanceTicket::where('status', 'in_progress')->count(),
            'completed' => MaintenanceTicket::where('status', 'completed')->count(),
            'total_cost' => (float) MaintenanceTicket::where('status', 'completed')->sum('cost'),
        ];
    }

    public function create(array $data): MaintenanceTicket
    {
        return MaintenanceTicket::create($data);
    }

    public function update(int $id, array $data): MaintenanceTicket
    {
        $ticket = MaintenanceTicket::findOrFail($id);
        $ticket->update($data);
        return $ticket->fresh();
    }

    public function delete(int $id): bool
    {
        $ticket = MaintenanceTicket::find($id);
        if (!$ticket) {
            return false;
        }
        return (bool) $ticket->delete();
    }
}
