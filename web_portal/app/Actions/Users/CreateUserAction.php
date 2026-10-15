<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

readonly class CreateUserAction
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private TelegramNotificationService $telegramService
    ) {}

    public function execute(array $validated): User
    {
        $validated['password'] = Hash::make((string) $validated['password']);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $user = $this->repository->create($validated);

        $adminName = Auth::user() ? Auth::user()->name : 'Admin';
        $this->telegramService->notifyUserCreatedOrAuthorized($user, $adminName, 'manual');

        return $user;
    }
}
