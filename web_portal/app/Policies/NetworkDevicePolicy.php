<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MonitoredNetworkDevice;
use App\Models\User;

/**
 * Política de autorización para Dispositivos de Red e Infraestructura.
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class NetworkDevicePolicy
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

    public function view(User $user, ?MonitoredNetworkDevice $device = null): bool
    {
        return $user->hasPermission('infra.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('infra.manage_devices');
    }

    public function update(User $user, ?MonitoredNetworkDevice $device = null): bool
    {
        return $user->hasPermission('infra.manage_devices');
    }

    public function delete(User $user, ?MonitoredNetworkDevice $device = null): bool
    {
        return $user->hasPermission('infra.manage_devices');
    }
}
