<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SnmpDevice;
use App\Models\User;

/**
 * Política de autorización para Dispositivos SNMP y Mapeo de Interfaces.
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class SnmpDevicePolicy
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
        return $user->hasPermission('snmp.view');
    }

    public function view(User $user, ?SnmpDevice $device = null): bool
    {
        return $user->hasPermission('snmp.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('snmp.manage');
    }

    public function update(User $user, ?SnmpDevice $device = null): bool
    {
        return $user->hasPermission('snmp.manage');
    }

    public function delete(User $user, ?SnmpDevice $device = null): bool
    {
        return $user->hasPermission('snmp.manage');
    }

    public function poll(User $user, ?SnmpDevice $device = null): bool
    {
        return $user->hasPermission('snmp.poll');
    }
}
