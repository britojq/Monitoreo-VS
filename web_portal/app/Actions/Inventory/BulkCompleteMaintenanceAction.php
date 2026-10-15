<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\MaintenanceTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

readonly class BulkCompleteMaintenanceAction
{
    public function execute(Request $request): int
    {
        $assetIds = $request->input('asset_ids', []);
        $completedAt = $request->filled('completed_at') ? Carbon::parse($request->input('completed_at')) : now();
        $notes = $request->input('resolution_notes') ?: 'Mantenimiento técnico ejecutado y certificado exitosamente.';
        $technicianId = $request->input('technician_id') ?: Auth::id();
        $year = $completedAt->format('Y');

        $completedCount = 0;

        DB::transaction(function () use ($assetIds, $completedAt, $notes, $technicianId, $year, $request, &$completedCount) {
            foreach ($assetIds as $assetId) {
                $asset = InventoryAsset::find($assetId);
                if (!$asset) {
                    continue;
                }

                $ticket = MaintenanceTicket::where('inventory_asset_id', $asset->id)
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->latest()
                    ->first();

                if ($ticket) {
                    $ticket->update([
                        'status' => 'completed',
                        'completed_at' => $completedAt,
                        'technician_user_id' => $technicianId,
                        'resolution_notes' => $notes,
                    ]);
                } else {
                    $countThisYear = MaintenanceTicket::where('ticket_code', 'like', "OS-{$year}-%")->count() + 1;
                    $ticketCode = sprintf("OS-%s-%04d", $year, $countThisYear);

                    MaintenanceTicket::create([
                        'ticket_code' => $ticketCode,
                        'inventory_asset_id' => $asset->id,
                        'title' => 'Mantenimiento Preventivo Ejecutado',
                        'description' => 'Mantenimiento masivo ejecutado para la sede ' . ($asset->monitoredSite?->name ?? 'General'),
                        'type' => 'preventive',
                        'priority' => 'medium',
                        'status' => 'completed',
                        'technician_user_id' => $technicianId,
                        'scheduled_at' => $completedAt,
                        'completed_at' => $completedAt,
                        'resolution_notes' => $notes,
                    ]);
                }

                $newStatus = !empty($asset->custodian_name) ? 'assigned' : 'active';
                $asset->update(['status' => $newStatus]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()?->name ?? 'Sistema',
                    'user_email' => Auth::user()?->email,
                    'user_role' => Auth::user()?->role,
                    'event' => 'updated',
                    'module' => 'inventory',
                    'auditable_type' => InventoryAsset::class,
                    'auditable_id' => $asset->id,
                    'entity_name' => $asset->name,
                    'entity_label' => $asset->asset_tag,
                    'description' => "Mantenimiento finalizado para equipo '{$asset->name}'. Estado restaurado a: {$newStatus}",
                    'ip_address' => $request->ip(),
                ]);

                $completedCount++;
            }
        });

        return $completedCount;
    }
}
