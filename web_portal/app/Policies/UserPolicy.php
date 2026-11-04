<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Política de autorización para el módulo de Gestión de Usuarios y Permisos.
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class UserPolicy
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

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('security.users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('security.users') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('security.users');
    }

    public function update(User $user, ?User $model = null): bool
    {
        return $user->hasPermission('security.users');
    }

    public function delete(User $user, ?User $model = null): bool
    {
        if ($model !== null && $user->id === $model->id) {
            return false;
        }

        return $user->hasPermission('security.users');
    }

    public function managePermissions(User $user, ?User $model = null): bool
    {
        return $user->hasPermission('security.permissions');
    }
}
