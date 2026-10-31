<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Política de autorización para el módulo NET Radar e Inspección de Tráfico.
 * Cumple con OWASP A01:2021 (Broken Access Control) y arquitectura RBAC de S.O.N.A.R.
 */
class NetRadarPolicy
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
     * Determina si el usuario puede consultar el radar de red y tráfico activo.
     */
    public function view(User $user): bool
    {
        return $user->hasPermission('netradar.view');
    }

    /**
     * Determina si el usuario puede forzar la captura y escaneo activo de tráfico.
     */
    public function scan(User $user): bool
    {
        return $user->hasPermission('netradar.scan');
    }

    /**
     * Determina si el usuario puede exportar reportes de red en Markdown.
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('netradar.export');
    }
}
