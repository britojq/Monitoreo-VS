<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Política de autorización para el módulo de Alertas, Correlaciones y Mantenimiento.
 * Cumple con OWASP A01:2021 (Broken Access Control) y arquitectura RBAC de S.O.N.A.R.
 */
class AlertPolicy
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
     * Determina si el usuario puede consultar el dashboard de alertas y su historial.
     */
    public function view(User $user): bool
    {
        return $user->hasPermission('alerts.view');
    }

    /**
     * Determina si el usuario puede reconocer (ACK) alertas activas.
     */
    public function acknowledge(User $user): bool
    {
        return $user->hasPermission('alerts.ack');
    }

    /**
     * Determina si el usuario puede silenciar alertas temporalmente.
     */
    public function silence(User $user): bool
    {
        return $user->hasPermission('alerts.ack');
    }

    /**
     * Determina si el usuario puede resolver alertas manualmente.
     * Esta acción está reservada exclusivamente para Administradores.
     */
    public function resolve(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina si el usuario puede forzar la re-evaluación inmediata de alertas.
     */
    public function evaluate(User $user): bool
    {
        return $user->hasPermission('alerts.rules');
    }

    /**
     * Determina si el usuario puede crear, editar o eliminar reglas de alerta o correlación.
     */
    public function manageRules(User $user): bool
    {
        return $user->hasPermission('alerts.rules');
    }

    /**
     * Determina si el usuario puede programar o revocar ventanas de mantenimiento.
     */
    public function manageMaintenance(User $user): bool
    {
        return $user->hasPermission('alerts.maintenance');
    }
}
