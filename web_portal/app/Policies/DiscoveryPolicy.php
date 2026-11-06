<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Política de autorización para el módulo de Descubrimiento de Red & Anti-Rogue.
 * Cumple con OWASP A01:2021 (Broken Access Control) y arquitectura RBAC de S.O.N.A.R.
 */
class DiscoveryPolicy
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
     * Determina si el usuario puede consultar los dispositivos descubiertos en la LAN.
     */
    public function view(User $user): bool
    {
        return $user->hasPermission('discovery.view');
    }

    /**
     * Determina si el usuario puede lanzar un escaneo CIDR en las subredes.
     */
    public function scan(User $user): bool
    {
        return $user->hasPermission('discovery.scan');
    }

    /**
     * Determina si el usuario puede autorizar o marcar dispositivos como Rogue.
     */
    public function authorize(User $user): bool
    {
        return $user->hasPermission('discovery.authorize');
    }
}
