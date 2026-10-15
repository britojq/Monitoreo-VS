<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\MaintenanceTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class BulkMaintenanceAction
{
    public function execute(Request $request): int
    {
        $assetIds = $request->input('asset_ids', []);
        $scheduledAt = Carbon::parse($request->input('scheduled_at'));
        $type = $request->input('type');
        $priority = $request->input('priority');
        $technicianId = $request->input('technician_id');
        $title = $request->input('title');
        $notes = $request->input('notes');

        $year = $scheduledAt->format('Y');
        $createdCount = 0;

        foreach ($assetIds as $assetId) {
            $asset = InventoryAsset::find($assetId);
            if (!$asset) {
                continue;
            }

            $countThisYear = MaintenanceTicket::where('ticket_code', 'like', "OS-{$year}-%")->count() + 1;
            $ticketCode = sprintf("OS-%s-%04d", $year, $countThisYear);

            MaintenanceTicket::create([
                'ticket_code' => $ticketCode,
                'inventory_asset_id' => $asset->id,
                'title' => $title ?: "Mantenimiento {$type} programado",
                'description' => $notes ?: "Mantenimiento masivo programado para la sede " . ($asset->monitoredSite?->name ?? 'General'),
                'type' => $type,
                'priority' => $priority,
                'status' => 'scheduled',
                'technician_user_id' => $technicianId,
                'scheduled_at' => $scheduledAt,
            ]);

            $asset->update(['status' => 'maintenance']);
            $createdCount++;
        }

        return $createdCount;
    }
}
