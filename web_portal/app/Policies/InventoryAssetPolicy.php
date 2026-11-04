<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryAsset;
use App\Models\User;

/**
 * Política de autorización para Activos de TI (ITAM).
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class InventoryAssetPolicy
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
        return $user->hasPermission('inventory.view');
    }

    public function view(User $user, ?InventoryAsset $asset = null): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function update(User $user, ?InventoryAsset $asset = null): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function delete(User $user, ?InventoryAsset $asset = null): bool
    {
        return $user->hasPermission('inventory.manage');
    }
}
