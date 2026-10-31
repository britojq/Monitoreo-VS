<?php

declare(strict_types=1);

namespace App\Actions\Alert;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Process;

class ManageAlertLifecycleAction
{
    public function getDetails(int $id): array
    {
        $alert = Alert::with(['rule', 'correlationGroup', 'parentAlert', 'acknowledgedByUser', 'notifications.escalationLevel'])
            ->findOrFail($id);

        $notifications = $alert->notifications->map(fn($n) => [
            'id' => $n->id,
            'channel' => strtoupper($n->channel),
            'target' => $n->target,
            'status' => $n->status === 'sent' ? 'Enviada' : ($n->status === 'failed' ? 'Fallida' : ucfirst($n->status)),
            'sent_at' => $n->sent_at ? $n->sent_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A',
            'error_message' => $n->error_message,
            'message_preview' => strip_tags($n->message_sent),
        ]);

        return [
            'id' => $alert->id,
            'entity_name' => $alert->entity_name,
            'entity_type' => $alert->entity_type,
            'entity_type_label' => $alert->entity_type_label,
            'entity_id' => $alert->entity_id,
            'condition_type' => $alert->condition_type,
            'condition_label' => $alert->condition_label,
            'severity' => $alert->severity,
            'severity_label' => $alert->severity_label,
            'status' => $alert->status,
            'status_label' => $alert->status_label,
            'current_escalation_level' => $alert->current_escalation_level,
            'value_at_trigger' => $alert->value_at_trigger,
            'threshold_value' => $alert->threshold_value,
            'message' => $alert->message,
            'notes' => $alert->notes,
            'fired_at' => $alert->fired_at ? $alert->fired_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A',
            'acknowledged_at' => $alert->acknowledged_at ? $alert->acknowledged_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : null,
            'acknowledged_by_user' => $alert->acknowledgedByUser ? $alert->acknowledgedByUser->name : null,
            'resolved_at' => $alert->resolved_at ? $alert->resolved_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : null,
            'resolved_by' => $alert->resolved_by === 'auto' ? 'Auto-Resuelta por el Sistema' : ($alert->resolved_by ?: 'Operador'),
            'duration_formatted' => $alert->duration_formatted,
            'is_correlated_suppressed' => (bool) $alert->is_correlated_suppressed,
            'parent_alert_name' => $alert->parentAlert ? $alert->parentAlert->entity_name : null,
            'correlation_group_name' => $alert->correlationGroup ? $alert->correlationGroup->name : null,
            'notifications' => $notifications,
        ];
    }

    public function acknowledge(int $id, ?User $user, string $notes, ?string $ip, ?string $userAgent): Alert
    {
        $alert = Alert::findOrFail($id);
        $alert->acknowledge($user?->id ?? 1, $notes);

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Operador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'acknowledged',
            'module' => 'alerts',
            'auditable_type' => Alert::class,
            'auditable_id' => $alert->id,
            'entity_name' => 'Alerta',
            'entity_label' => "Alerta #{$id} ({$alert->entity_name})",
            'description' => "Alerta #{$id} ({$alert->entity_name}) reconocida por " . ($user?->name ?? 'Usuario') . ($notes ? ": {$notes}" : ''),
            'new_values' => ['notes' => $notes, 'status' => 'acknowledged'],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $alert;
    }

    public function resolve(int $id, ?User $user, string $notes, ?string $ip, ?string $userAgent): Alert
    {
        $alert = Alert::findOrFail($id);
        $alert->resolve('manual', $notes);

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'resolved',
            'module' => 'alerts',
            'auditable_type' => Alert::class,
            'auditable_id' => $alert->id,
            'entity_name' => 'Alerta',
            'entity_label' => "Alerta #{$id} ({$alert->entity_name})",
            'description' => "Alerta #{$id} ({$alert->entity_name}) resuelta manualmente por " . ($user?->name ?? 'Usuario') . ($notes ? ": {$notes}" : ''),
            'new_values' => ['notes' => $notes, 'status' => 'resolved', 'resolved_type' => 'manual'],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $alert;
    }

    public function silence(int $id, ?User $user, int $minutes, ?string $ip, ?string $userAgent): Alert
    {
        $alert = Alert::findOrFail($id);
        $cleanMinutes = $minutes > 0 ? $minutes : 60;
        $startsAt = Carbon::now();
        $endsAt = Carbon::now()->addMinutes($cleanMinutes);

        MaintenanceWindow::create([
            'title' => "Silenciado: {$alert->entity_name}",
            'description' => "Silenciado temporal por {$cleanMinutes} min desde la consola web.",
            'entity_type' => $alert->entity_type,
            'entity_id' => $alert->entity_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => $user?->id,
            'is_active' => true,
        ]);

        $alert->status = 'suppressed';
        $alert->notes = trim(($alert->notes ? $alert->notes . "\n" : '') . "[Silenciado por {$cleanMinutes} min hasta " . $endsAt->format('d/m/Y H:i') . "]");
        $alert->save();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Operador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'silenced',
            'module' => 'alerts',
            'auditable_type' => Alert::class,
            'auditable_id' => $alert->id,
            'entity_name' => 'Alerta',
            'entity_label' => "Alerta #{$id} ({$alert->entity_name})",
            'description' => "Alerta #{$id} ({$alert->entity_name}) silenciada por {$cleanMinutes} minutos por " . ($user?->name ?? 'Usuario'),
            'new_values' => ['minutes' => $cleanMinutes, 'ends_at' => $endsAt->toIso8601String(), 'status' => 'suppressed'],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $alert;
    }

    public function evaluateNow(): void
    {
        Process::run('/usr/local/bin/estatus alertas --evaluate --escalate');
    }
}
