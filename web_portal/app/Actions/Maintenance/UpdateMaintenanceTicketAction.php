<?php

declare(strict_types=1);

namespace App\Actions\Maintenance;

use App\Contracts\Repositories\MaintenanceTicketRepositoryInterface;
use App\Models\AuditLog;
use App\Models\InventoryComponentHistory;
use App\Models\MaintenanceTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

readonly class UpdateMaintenanceTicketAction
{
    public function __construct(
        private MaintenanceTicketRepositoryInterface $repository
    ) {}

    public function execute(MaintenanceTicket $ticket, array $validated, bool $warrantyApplied, ?string $ip, ?string $userAgent): MaintenanceTicket
    {
        return DB::transaction(function () use ($ticket, $validated, $warrantyApplied, $ip, $userAgent) {
            $oldStatus = $ticket->status;
            $newStatus = $validated['status'];

            $updateData = [
                'status' => $newStatus,
                'priority' => $validated['priority'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : $ticket->scheduled_at,
                'technician_user_id' => $validated['technician_user_id'] ?? $ticket->technician_user_id,
                'resolution_notes' => $validated['resolution_notes'] ?? null,
                'cost' => $validated['cost'] ?? $ticket->cost,
                'warranty_applied' => $warrantyApplied,
                'parts_replaced_json' => $validated['parts_replaced'] ?? $ticket->parts_replaced_json,
            ];

            if ($newStatus === 'in_progress' && !$ticket->started_at) {
                $updateData['started_at'] = now();
                $ticket->asset?->update(['status' => 'in_maintenance']);
            }

            if ($newStatus === 'completed') {
                if (!$ticket->completed_at) {
                    $updateData['completed_at'] = now();
                }
                if ($ticket->asset && $ticket->asset->status === 'in_maintenance') {
                    $targetStatus = $ticket->asset->custodian_name ? 'assigned' : 'active';
                    $ticket->asset->update(['status' => $targetStatus]);
                }

                if (!empty($validated['parts_replaced'])) {
                    foreach ($validated['parts_replaced'] as $part) {
                        InventoryComponentHistory::create([
                            'inventory_asset_id' => $ticket->inventory_asset_id,
                            'action' => 'replaced',
                            'component_type' => 'maintenance_part',
                            'old_value' => null,
                            'new_value' => $part,
                            'reason' => "Sustituido en Ticket {$ticket->ticket_code}: {$part['name']}",
                            'registered_by_user_id' => Auth::id(),
                            'recorded_at' => now(),
                        ]);
                    }
                }
            }

            if ($newStatus === 'canceled' && $ticket->asset && $ticket->asset->status === 'in_maintenance') {
                $targetStatus = $ticket->asset->custodian_name ? 'assigned' : 'active';
                $ticket->asset->update(['status' => $targetStatus]);
            }

            $updatedTicket = $this->repository->update($ticket->id, $updateData);

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name ?? 'Sistema',
                'user_email' => Auth::user()?->email ?? 'system@local',
                'user_role' => Auth::user()?->role ?? 'admin',
                'event' => 'updated',
                'module' => 'inventory',
                'auditable_type' => MaintenanceTicket::class,
                'auditable_id' => $ticket->id,
                'entity_name' => $ticket->ticket_code,
                'entity_label' => $ticket->title,
                'description' => "Ticket {$ticket->ticket_code} actualizado ({$oldStatus} -> {$newStatus})",
                'new_values' => $updatedTicket->toArray(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            return $updatedTicket;
        });
    }
}
