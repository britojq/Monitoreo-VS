<?php

declare(strict_types=1);

namespace App\Actions\Maintenance;

use App\Contracts\Repositories\MaintenanceTicketRepositoryInterface;
use App\Models\AuditLog;
use App\Models\MaintenanceTicket;
use Illuminate\Support\Facades\Auth;

readonly class DeleteMaintenanceTicketAction
{
    public function __construct(
        private MaintenanceTicketRepositoryInterface $repository
    ) {}

    public function execute(MaintenanceTicket $ticket, ?string $ip, ?string $userAgent): string
    {
        $code = $ticket->ticket_code;
        $id = $ticket->id;

        $this->repository->delete($id);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email ?? 'system@local',
            'user_role' => Auth::user()?->role ?? 'admin',
            'event' => 'deleted',
            'module' => 'inventory',
            'auditable_type' => MaintenanceTicket::class,
            'auditable_id' => $id,
            'entity_name' => $code,
            'description' => "Ticket de mantenimiento {$code} eliminado.",
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $code;
    }
}
