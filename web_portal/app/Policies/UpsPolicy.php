<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Política de autorización para el módulo de Energía y Respaldo UPS.
 * Cumple con OWASP A01:2021 (Broken Access Control) y arquitectura RBAC de S.O.N.A.R.
 */
class UpsPolicy
{
    /**
     * Comprobación previa: Los administradores tienen acceso global.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determina si el usuario puede consultar la telemetría en vivo del UPS.
     */
    public function view(User $user): bool
    {
        return $user->hasPermission('ups.view');
    }

    /**
     * Determina si el usuario puede configurar parámetros de alerta y notificaciones del UPS.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('ups.manage');
    }
}
