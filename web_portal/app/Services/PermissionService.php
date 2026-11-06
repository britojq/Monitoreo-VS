<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Security\PermissionCatalog;

/**
 * Servicio de evaluación y sanitización de permisos RBAC.
 * Delega el catálogo maestro a PermissionCatalog cumpliendo Single Responsibility.
 */
class PermissionService
{
    /**
     * Catálogo maestro de permisos granulares clasificados por módulo
     */
    public const array MODULES = PermissionCatalog::MODULES;

    /**
     * Retorna todos los módulos y permisos
     */
    public static function getModules(): array
    {
        return PermissionCatalog::getModules();
    }

    /**
     * Retorna una lista plana de todas las claves de permisos válidas
     */
    public static function getAllPermissionKeys(): array
    {
        return PermissionCatalog::getAllPermissionKeys();
    }

    /**
     * Retorna la lista de permisos predeterminados para el rol operador
     */
    public static function getDefaultOperatorPermissions(): array
    {
        return PermissionCatalog::getDefaultOperatorPermissions();
    }

    /**
     * Retorna la lista de permisos predeterminados para el rol usuario (solo consulta de sus equipos)
     */
    public static function getDefaultUserPermissions(): array
    {
        return PermissionCatalog::getDefaultUserPermissions();
    }

    /**
     * Comprueba si una clave de permiso es válida
     */
    public static function isValidPermission(string $permissionKey): bool
    {
        return in_array($permissionKey, self::getAllPermissionKeys(), true);
    }

    /**
     * Sanitiza y filtra una lista de permisos para descartar claves inexistentes
     */
    public static function sanitizePermissions(array $permissions): array
    {
        $validKeys = self::getAllPermissionKeys();
        return array_values(array_unique(array_intersect($permissions, $validKeys)));
    }
}
