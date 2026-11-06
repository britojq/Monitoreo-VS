<?php

namespace App\Services\Inventory;

use App\Contracts\AssetTimelineInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponentHistory;
use App\Models\MaintenanceTicket;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Servicio unificado de auditoría forense y trazabilidad cronológica de activos ITAM (MariaDB SSOT).
 */
class AssetTimelineService implements AssetTimelineInterface
{
    /**
     * Retorna la línea de tiempo cronológica unificada de un activo específico.
     */
    public function getAssetTimeline(InventoryAsset $asset): Collection
    {
        $timeline = collect();

        // 1. Asignaciones y Pases de Movilización
        $assignments = $asset->assignments()
            ->with(['user', 'deliveredByUser', 'site'])
            ->get();

        foreach ($assignments as $a) {
            $assignedDate = $a->assigned_at ? Carbon::parse($a->assigned_at) : ($a->created_at ? Carbon::parse($a->created_at) : now());
            $deliveredBy = $a->deliveredByUser?->name ?: ($a->user?->name ?: 'Sistema');
            $recipient = $a->custodian_name ?: 'Funcionario Receptor';

            // Evento: Asignación o Movilización
            $isTransfer = $a->movement_type === 'transfer';
            $isLoan = $a->movement_type === 'loan';
            
            $timeline->push([
                'id' => 'assign_' . $a->id,
                'type' => $isTransfer ? 'transfer' : ($isLoan ? 'loan' : 'assignment'),
                'type_label' => $a->movement_type_label,
                'title' => "Acta {$a->assignment_code} ({$a->movement_type_label})",
                'description' => "Asignado a: {$recipient} (" . ($a->custodian_department ?? 'N/A') . ")" . ($a->site ? " • Sede: {$a->site->name}" : ""),
                'timestamp' => $assignedDate,
                'timestamp_formatted' => $assignedDate->format('d/m/Y H:i'),
                'time_ago' => $assignedDate->diffForHumans(),
                'user_name' => $deliveredBy,
                'badge_class' => $isTransfer 
                    ? 'bg-blue-950/80 text-blue-300 border border-blue-500/40'
                    : ($isLoan ? 'bg-amber-950/80 text-amber-300 border border-amber-500/40' : 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40'),
                'icon' => $isTransfer ? 'local_shipping' : ($isLoan ? 'schedule' : 'assignment_ind'),
                'details' => [
                    'assignment_code' => $a->assignment_code,
                    'status' => $a->status,
                    'has_signed_scan' => $a->has_signed_scan,
                    'scan_compliance_status' => $a->scan_compliance_status,
                    'scan_compliance_label' => $a->scan_compliance_label,
                    'show_url' => route('admin.inventory.assignments.show', $a->id),
                    'pdf_url' => route('admin.inventory.assignments.pdf', $a->id),
                ],
            ]);

            // Evento: Devolución registrada
            if ($a->returned_at) {
                $returnedDate = Carbon::parse($a->returned_at);
                $timeline->push([
                    'id' => 'return_' . $a->id,
                    'type' => 'return',
                    'type_label' => 'Devolución de Activo',
                    'title' => "Devolución de Activo • Acta {$a->assignment_code}",
                    'description' => "Recepción del equipo desincorporado o devuelto por {$recipient}. Reintegrado a disponibilidad.",
                    'timestamp' => $returnedDate,
                    'timestamp_formatted' => $returnedDate->format('d/m/Y H:i'),
                    'time_ago' => $returnedDate->diffForHumans(),
                    'user_name' => $deliveredBy,
                    'badge_class' => 'bg-cyan-950/80 text-cyan-300 border border-cyan-500/40',
                    'icon' => 'assignment_returned',
                    'details' => [
                        'assignment_code' => $a->assignment_code,
                        'show_url' => route('admin.inventory.assignments.show', $a->id),
                    ],
                ]);
            }
        }

        // 2. Modificaciones de Hardware & Detección de Drift
        $drifts = $asset->componentHistories()
            ->with('registeredByUser')
            ->get();

        foreach ($drifts as $d) {
            $driftDate = $d->recorded_at ? Carbon::parse($d->recorded_at) : now();
            $actionLabel = match($d->action) {
                'drift_detected' => 'Drift de Hardware Detectado',
                'installed' => 'Componente Instalado',
                'removed' => 'Componente Retirado',
                'modified' => 'Componente Modificado',
                default => ucfirst(str_replace('_', ' ', $d->action)),
            };

            $compLabel = strtoupper($d->component_type);
            $icon = match($d->component_type) {
                'ram' => 'developer_board',
                'cpu' => 'memory',
                'disk' => 'hard_drive',
                'nic' => 'settings_ethernet',
                'gpu' => 'videocam',
                default => 'hardware',
            };

            $badgeClass = match($d->action) {
                'drift_detected' => 'bg-rose-950/80 text-rose-300 border border-rose-500/40 animate-pulse',
                'installed' => 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40',
                'removed' => 'bg-amber-950/80 text-amber-300 border border-amber-500/40',
                default => 'bg-purple-950/80 text-purple-300 border border-purple-500/40',
            };

            $timeline->push([
                'id' => 'drift_' . $d->id,
                'type' => 'drift',
                'type_label' => $actionLabel,
                'title' => "Hardware: {$compLabel} • {$actionLabel}",
                'description' => $d->reason ?: "Modificación detectada en especificaciones de {$compLabel}.",
                'timestamp' => $driftDate,
                'timestamp_formatted' => $driftDate->format('d/m/Y H:i'),
                'time_ago' => $driftDate->diffForHumans(),
                'user_name' => $d->registeredByUser?->name ?: 'GLPI / Sondeo Automático',
                'badge_class' => $badgeClass,
                'icon' => $icon,
                'details' => [
                    'action' => $d->action,
                    'component_type' => $d->component_type,
                    'old_value' => $d->old_value,
                    'new_value' => $d->new_value,
                    'reason' => $d->reason,
                ],
            ]);
        }

        // 3. Mantenimientos Técnicos & Tickets
        $tickets = $asset->maintenanceTickets()
            ->with('technician')
            ->get();

        foreach ($tickets as $t) {
            $ticketDate = $t->completed_at 
                ? Carbon::parse($t->completed_at) 
                : ($t->started_at ? Carbon::parse($t->started_at) : ($t->scheduled_at ? Carbon::parse($t->scheduled_at) : ($t->created_at ? Carbon::parse($t->created_at) : now())));

            $techName = $t->technician?->name ?: 'Soporte Técnico';

            $timeline->push([
                'id' => 'ticket_' . $t->id,
                'type' => 'maintenance',
                'type_label' => "Mantenimiento " . ($t->type_label ?? 'Técnico'),
                'title' => "Ticket {$t->ticket_code}: {$t->title}",
                'description' => "Estado: {$t->status_label} • Técnico: {$techName}" . ($t->resolution_notes ? " • Resolución: {$t->resolution_notes}" : ""),
                'timestamp' => $ticketDate,
                'timestamp_formatted' => $ticketDate->format('d/m/Y H:i'),
                'time_ago' => $ticketDate->diffForHumans(),
                'user_name' => $techName,
                'badge_class' => match($t->status) {
                    'completed' => 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40',
                    'in_progress' => 'bg-amber-950/80 text-amber-300 border border-amber-500/40',
                    'canceled' => 'bg-rose-950/80 text-rose-300 border border-rose-500/40',
                    default => 'bg-purple-950/80 text-purple-300 border border-purple-500/40',
                },
                'icon' => 'build',
                'details' => [
                    'ticket_code' => $t->ticket_code,
                    'status' => $t->status,
                    'status_label' => $t->status_label,
                    'resolution_notes' => $t->resolution_notes,
                    'parts_replaced' => $t->parts_replaced_json,
                    'show_url' => route('admin.inventory.maintenance.show', $t->id),
                ],
            ]);
        }

        // 4. Pistas de Auditoría Administrativa (AuditLog)
        $auditLogs = AuditLog::where(function ($q) use ($asset) {
            $q->where('auditable_type', 'App\\Models\\InventoryAsset')
              ->where('auditable_id', $asset->id);
        })->orWhere(function ($q) use ($asset) {
            $q->where('module', 'inventory')
              ->where('entity_label', $asset->asset_tag);
        })->get();

        foreach ($auditLogs as $log) {
            $logDate = Carbon::parse($log->created_at);

            $timeline->push([
                'id' => 'audit_' . $log->id,
                'type' => 'audit',
                'type_label' => $log->event_label,
                'title' => "Auditoría: " . $log->event_label,
                'description' => $log->description ?: "Cambio administrativo registrado en MariaDB.",
                'timestamp' => $logDate,
                'timestamp_formatted' => $logDate->format('d/m/Y H:i'),
                'time_ago' => $logDate->diffForHumans(),
                'user_name' => $log->user_name ?: 'Sistema',
                'badge_class' => $log->event_badge_class,
                'icon' => match($log->event) {
                    'approved' => 'verified',
                    'created' => 'add_circle',
                    'updated' => 'edit_note',
                    'deleted' => 'delete_forever',
                    default => 'policy',
                },
                'details' => [
                    'event' => $log->event,
                    'ip_address' => $log->ip_address,
                    'changed_fields' => $log->changed_fields,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                ],
            ]);
        }

        // Ordenar en orden cronológico inverso (el más reciente primero)
        return $timeline->sortByDesc(fn ($item) => $item['timestamp']->timestamp)->values();
    }

    /**
     * Retorna el feed global paginado de auditoría y trazabilidad de activos ITAM.
     */
    public function getGlobalItamAuditFeed(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::where(function ($q) {
            $q->whereIn('module', ['inventory', 'devices', 'lifecycle'])
              ->orWhereIn('auditable_type', [
                  'App\\Models\\InventoryAsset',
                  'App\\Models\\InventoryAssignment',
                  'App\\Models\\MaintenanceTicket',
                  'App\\Models\\InventoryPeripheral',
                  'App\\Models\\InventoryComponentHistory',
              ]);
        });

        // Búsqueda libre
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                  ->orWhere('entity_name', 'like', "%{$s}%")
                  ->orWhere('entity_label', 'like', "%{$s}%")
                  ->orWhere('user_name', 'like', "%{$s}%");
            });
        }

        // Filtro por tipo de evento
        if (!empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        // Filtro por activo específico
        if (!empty($filters['asset_id'])) {
            $asset = InventoryAsset::find($filters['asset_id']);
            if ($asset) {
                $query->where(function ($q) use ($asset) {
                    $q->where(function ($sq) use ($asset) {
                        $sq->where('auditable_type', 'App\\Models\\InventoryAsset')
                           ->where('auditable_id', $asset->id);
                    })->orWhere('entity_label', $asset->asset_tag)
                      ->orWhere('entity_name', $asset->name);
                });
            }
        }

        // Filtro por fecha desde
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        // Filtro por fecha hasta
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();
    }

    /**
     * Retorna métricas KPI de auditoría y ciclo de vida de activos ITAM.
     */
    public function getAuditKpis(): array
    {
        $today = Carbon::today();
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $totalEvents = AuditLog::where(function ($q) {
            $q->whereIn('module', ['inventory', 'devices', 'lifecycle'])
              ->orWhereIn('auditable_type', [
                  'App\\Models\\InventoryAsset',
                  'App\\Models\\InventoryAssignment',
                  'App\\Models\\MaintenanceTicket',
                  'App\\Models\\InventoryPeripheral',
                  'App\\Models\\InventoryComponentHistory',
              ]);
        })->count();

        $driftsCount = InventoryComponentHistory::count();
        $activeAssignments = InventoryAssignment::where('status', 'active')->count();

        // Discrepancias / Actas vencidas o con hardware modificado
        $outdatedAssignmentsCount = InventoryAsset::where('status', 'assigned')->get()
            ->filter(fn ($a) => $a->has_outdated_assignment || $a->is_scan_overdue)
            ->count();

        $eventsLast30Days = AuditLog::where(function ($q) {
            $q->whereIn('module', ['inventory', 'devices', 'lifecycle'])
              ->orWhereIn('auditable_type', [
                  'App\\Models\\InventoryAsset',
                  'App\\Models\\InventoryAssignment',
                  'App\\Models\\MaintenanceTicket',
                  'App\\Models\\InventoryPeripheral',
                  'App\\Models\\InventoryComponentHistory',
              ]);
        })->where('created_at', '>=', $thirtyDaysAgo)->count();

        return [
            'total_events' => $totalEvents,
            'drifts_count' => $driftsCount,
            'active_assignments' => $activeAssignments,
            'outdated_assignments' => $outdatedAssignmentsCount,
            'events_last_30_days' => $eventsLast30Days,
        ];
    }
}
