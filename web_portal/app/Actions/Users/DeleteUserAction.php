<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

readonly class DeleteUserAction
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function execute(User $user): array
    {
        if ($user->id === Auth::id()) {
            return [
                'success' => false,
                'message' => 'No puede eliminar su propia cuenta de usuario en sesión.',
            ];
        }

        if ($user->email === 'britojq@gmail.com') {
            return [
                'success' => false,
                'message' => 'El Administrador Principal del sistema no puede ser eliminado.',
            ];
        }

        $this->repository->delete($user->id);

        return [
            'success' => true,
            'message' => 'Usuario eliminado exitosamente.',
        ];
    }
}
