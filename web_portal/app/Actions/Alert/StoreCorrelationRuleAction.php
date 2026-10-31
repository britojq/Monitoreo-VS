<?php

declare(strict_types=1);

namespace App\Actions\Alert;

use App\Models\AlertCorrelationGroup;
use App\Models\AlertCorrelationMember;
use App\Models\AuditLog;
use App\Models\User;

class StoreCorrelationRuleAction
{
    public function execute(array $validated, ?User $user, ?string $ip, ?string $userAgent, array $children = []): AlertCorrelationGroup
    {
        $validated['is_active'] = true;
        $group = AlertCorrelationGroup::create($validated);

        if (!empty($children)) {
            foreach ($children as $child) {
                if (!empty($child['type']) && !empty($child['id'])) {
                    AlertCorrelationMember::create([
                        'correlation_group_id' => $group->id,
                        'child_entity_type' => $child['type'],
                        'child_entity_id' => (int) $child['id'],
                    ]);
                }
            }
        }

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'created',
            'module' => 'alerts',
            'auditable_type' => AlertCorrelationGroup::class,
            'auditable_id' => $group->id,
            'entity_name' => 'Grupo de Correlación',
            'entity_label' => $group->name,
            'description' => "Grupo de correlación '{$group->name}' creado por " . ($user?->name ?? 'Usuario'),
            'new_values' => $group->toArray(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $group;
    }
}
