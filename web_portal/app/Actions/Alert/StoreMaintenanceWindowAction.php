<?php

declare(strict_types=1);

namespace App\Actions\Alert;

use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\User;

class StoreMaintenanceWindowAction
{
    public function execute(array $validated, ?User $user, ?string $ip, ?string $userAgent): MaintenanceWindow
    {
        $validated['created_by'] = $user?->id;
        $validated['is_active'] = true;

        $window = MaintenanceWindow::create($validated);

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'created',
            'module' => 'alerts',
            'auditable_type' => MaintenanceWindow::class,
            'auditable_id' => $window->id,
            'entity_name' => 'Ventana de Mantenimiento',
            'entity_label' => $window->title,
            'description' => "Ventana de mantenimiento '{$window->title}' creada por " . ($user?->name ?? 'Usuario'),
            'new_values' => $window->toArray(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $window;
    }
}
