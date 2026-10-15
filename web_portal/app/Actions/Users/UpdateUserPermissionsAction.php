<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\PermissionService;

readonly class UpdateUserPermissionsAction
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function execute(User $user, bool $resetToDefault, ?array $rawPermissions): array
    {
        if ($user->email === 'britojq@gmail.com') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'El Administrador Principal conserva acceso total irrevocable (*).',
            ];
        }

        if ($resetToDefault) {
            $user->update(['permissions' => null]);
            $msg = "Permisos del usuario [{$user->name}] restablecidos a los valores predeterminados de su rol ({$user->role}).";
        } else {
            $sanitized = PermissionService::sanitizePermissions($rawPermissions ?? []);
            $user->update(['permissions' => $sanitized]);
            $msg = 'Se asignaron ' . count($sanitized) . " permiso(s) al usuario [{$user->name}].";
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => $msg,
            'effective_permissions' => $user->getEffectivePermissions(),
            'is_default' => is_null($user->permissions),
        ];
    }
}
