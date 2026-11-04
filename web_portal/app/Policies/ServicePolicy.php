<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MonitoredService;
use App\Models\User;

/**
 * Política de autorización para Servicios Corporativos Monitoreados.
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class ServicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('infra.view');
    }

    public function view(User $user, ?MonitoredService $service = null): bool
    {
        return $user->hasPermission('infra.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('infra.manage_services');
    }

    public function update(User $user, ?MonitoredService $service = null): bool
    {
        return $user->hasPermission('infra.manage_services');
    }

    public function delete(User $user, ?MonitoredService $service = null): bool
    {
        return $user->hasPermission('infra.manage_services');
    }
}
