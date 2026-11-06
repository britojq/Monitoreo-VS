<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MonitoredSite;
use App\Models\User;

/**
 * Política de autorización para Sedes y Enlaces WAN.
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class SitePolicy
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

    public function view(User $user, ?MonitoredSite $site = null): bool
    {
        return $user->hasPermission('infra.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('infra.manage_sites');
    }

    public function update(User $user, ?MonitoredSite $site = null): bool
    {
        return $user->hasPermission('infra.manage_sites');
    }

    public function delete(User $user, ?MonitoredSite $site = null): bool
    {
        return $user->hasPermission('infra.manage_sites');
    }
}
