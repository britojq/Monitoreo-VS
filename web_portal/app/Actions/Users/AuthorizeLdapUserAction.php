<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\AuditLog;
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

        // Bitácora de auditoría en MariaDB SSOT (audit_logs)
        $adminName = Auth::user() ? Auth::user()->name : 'Admin';
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => $adminName,
                'event' => 'LDAP_USER_AUTHORIZED',
                'module' => 'users',
                'entity_name' => $user->name,
                'description' => "Usuario LDAP {$ldapUser['name']} ({$uid}) autorizado manualmente con rol '{$role}'",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'new_values' => [
                    'authorized_user_id' => $user->id,
                    'uid' => $uid,
                    'email' => $ldapUser['email'],
                    'role' => $role,
                    'area' => $ldapUser['description'] ?? 'General',
                ],
            ]);
        } catch (\Throwable $e) {
            // Silencioso ante contingencias en DB
        }

        // Notificar por Telegram
        $this->telegramService->notifyUserCreatedOrAuthorized($user, $adminName, 'ldap_preauth');

        return [
            'success' => true,
            'message' => $msg,
            'user' => $user,
        ];
    }
}
