<?php

declare(strict_types=1);

namespace App\Actions\Alert;

use App\Models\AlertEscalationLevel;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\User;

class StoreAlertRuleAction
{
    public function execute(array $validated, ?User $user, ?string $ip, ?string $userAgent): AlertRule
    {
        $rule = AlertRule::create($validated);

        AlertEscalationLevel::create([
            'alert_rule_id' => $rule->id,
            'level' => 1,
            'delay_minutes' => 0,
            'channel' => 'telegram',
            'target_type' => 'user',
            'target_id' => $user?->id ?? 1,
            'target_external' => '<OWNER_TELEGRAM_ID>',
            'is_active' => true,
        ]);

        AlertEscalationLevel::create([
            'alert_rule_id' => $rule->id,
            'level' => 2,
            'delay_minutes' => 15,
            'channel' => 'telegram',
            'target_type' => 'user',
            'target_id' => $user?->id ?? 1,
            'target_external' => '<OWNER_TELEGRAM_ID>',
            'is_active' => true,
        ]);

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'created',
            'module' => 'alerts',
            'auditable_type' => AlertRule::class,
            'auditable_id' => $rule->id,
            'entity_name' => 'Regla de Alerta',
            'entity_label' => $rule->name,
            'description' => "Regla de alerta '{$rule->name}' creada por " . ($user?->name ?? 'Usuario'),
            'new_values' => $rule->toArray(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $rule;
    }
}
