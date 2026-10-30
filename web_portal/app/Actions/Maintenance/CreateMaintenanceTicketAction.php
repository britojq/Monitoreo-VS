<?php

declare(strict_types=1);

namespace App\Actions\Maintenance;

use App\Contracts\Repositories\MaintenanceTicketRepositoryInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\MaintenanceTicket;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

readonly class CreateMaintenanceTicketAction
{
    public function __construct(
        private MaintenanceTicketRepositoryInterface $repository,
        private TelegramNotificationService $telegramService
    ) {}

    public function execute(array $validated, ?string $ip, ?string $userAgent): MaintenanceTicket
    {
        $year = date('Y');
        $prefix = "MNT-{$year}";
        $count = MaintenanceTicket::where('ticket_code', 'like', "{$prefix}-%")->count() + 1;
        $code = sprintf("%s-%04d", $prefix, $count);

        $asset = InventoryAsset::findOrFail($validated['inventory_asset_id']);

        return DB::transaction(function () use ($validated, $code, $asset, $ip, $userAgent) {
            $ticket = $this->repository->create([
                'ticket_code' => $code,
                'inventory_asset_id' => $asset->id,
                'type' => $validated['type'],
                'status' => 'scheduled',
                'priority' => $validated['priority'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : now(),
                'technician_user_id' => $validated['technician_user_id'] ?? Auth::id(),
                'cost' => $validated['cost'] ?? 0.00,
            ]);

            if ($validated['type'] === 'corrective' || in_array($validated['priority'], ['high', 'critical'], true)) {
                $asset->update(['status' => 'in_maintenance']);
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name ?? 'Sistema',
                'user_email' => Auth::user()?->email ?? 'system@local',
                'user_role' => Auth::user()?->role ?? 'admin',
                'event' => 'created',
                'module' => 'inventory',
                'auditable_type' => MaintenanceTicket::class,
                'auditable_id' => $ticket->id,
                'entity_name' => $ticket->ticket_code,
                'entity_label' => $ticket->title,
                'description' => "Ticket {$code} creado para activo [{$asset->asset_tag}] ({$validated['type']}): {$validated['title']}",
                'new_values' => $ticket->toArray(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            try {
                $this->telegramService->notifyMaintenanceTicketOpened($ticket);
            } catch (\Throwable $e) {
                // Notificación silenciosa
            }

            return $ticket;
        });
    }
}
