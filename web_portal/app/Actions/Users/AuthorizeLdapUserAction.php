<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\LdapAuthService;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

readonly class AuthorizeLdapUserAction
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private LdapAuthService $ldapService,
        private TelegramNotificationService $telegramService
    ) {}

    public function execute(string $uid, string $role): array
    {
        $uid = strtoupper(trim($uid));
        $ldapUser = $this->ldapService->findUserByUid($uid);

        if (!$ldapUser) {
            return [
                'success' => false,
                'message' => "El usuario {$uid} no fue encontrado en el servidor LDAP corporativo.",
                'user' => null,
            ];
        }

        $user = User::where('username', $uid)
            ->orWhere('email', $ldapUser['email'])
            ->first();

        if ($user) {
            $user->update([
                'name' => $ldapUser['name'],
                'username' => $uid,
                'email' => $ldapUser['email'],
                'role' => $role,
                'is_active' => true,
                'ban_reason' => null,
                'banned_at' => null,
            ]);
            $msg = "El usuario LDAP {$ldapUser['name']} ({$uid}) fue actualizado y reactivado exitosamente con rol '{$role}'.";
        } else {
            $user = User::create([
                'name' => $ldapUser['name'],
                'username' => $uid,
                'email' => $ldapUser['email'],
                'password' => Hash::make(Str::random(32)),
                'role' => $role,
                'is_active' => true,
            ]);
            $msg = "El usuario LDAP {$ldapUser['name']} ({$uid}) ha sido autorizado e incorporado exitosamente con rol '{$role}'.";
        }

        // Bitácora de auditoría
        $now = now()->format('Y-m-d H:i:s');
        $adminName = Auth::user() ? Auth::user()->name : 'Admin';
        $logLine = sprintf(
            "[%s] 👤 AUTORIZACIÓN MANUAL LDAP | Admin: %s | Usuario: %s (UID: %s, Email: %s, Rol: %s) | Área: %s\n",
            $now,
            $adminName,
            $ldapUser['name'],
            $uid,
            $ldapUser['email'],
            $role,
            $ldapUser['description'] ?? 'General'
        );
        @file_put_contents('/scripts/telegram-admin-bot/audit/intentos_acceso.log', $logLine, FILE_APPEND | LOCK_EX);

        // Notificar por Telegram
        $this->telegramService->notifyUserCreatedOrAuthorized($user, $adminName, 'ldap_preauth');

        return [
            'success' => true,
            'message' => $msg,
            'user' => $user,
        ];
    }
}
