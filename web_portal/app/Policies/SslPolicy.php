<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Política de autorización para el módulo de Certificados SSL/TLS.
 * Cumple con OWASP A01:2021 (Broken Access Control) y arquitectura RBAC de S.O.N.A.R.
 */
class SslPolicy
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
     * Determina si el usuario puede consultar la vigencia de certificados SSL/TLS.
     */
    public function view(User $user): bool
    {
        return $user->hasPermission('ssl.view');
    }

    /**
     * Determina si el usuario puede registrar o eliminar certificados monitoreados.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('ssl.manage');
    }

    /**
     * Determina si el usuario puede forzar la re-inspección manual de certificados en caliente.
     */
    public function recheck(User $user): bool
    {
        return $user->hasPermission('ssl.recheck');
    }
}
