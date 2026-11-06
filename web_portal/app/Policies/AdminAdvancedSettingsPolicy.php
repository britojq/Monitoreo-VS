<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Política de autorización para Parámetros Avanzados y Configuración Crítica del Sistema.
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class AdminAdvancedSettingsPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('security.advanced');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('security.advanced');
    }
}
