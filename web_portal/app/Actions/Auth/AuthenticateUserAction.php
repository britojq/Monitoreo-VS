<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\AuditService;
use App\Services\EntityResolverService;
use App\Services\LdapAuthService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthenticateUserAction
{
    public function __construct(
        protected LdapAuthService $ldapService,
        protected EntityResolverService $entityResolver,
        protected TelegramNotificationService $telegramService
    ) {}

    /**
     * Autentica al usuario vía base de datos local o Directorio Activo LDAP con provisión JIT.
     *
     * @param Request $request
     * @return array{success: bool, message?: string}
     */
    public function execute(Request $request): array
    {
        $loginValue = trim((string) $request->input('login', $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $remember = $request->boolean('remember');

        $throttleKey = 'login-attempt:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return [
                'success' => false,
                'message' => "Demasiados intentos fallidos. Por favor espere {$seconds} segundos antes de reintentar.",
            ];
        }

        // 1. ADMINISTRADOR LOCAL (Autenticación Local Failsafe por Correo)
        $isEmail = (bool) filter_var($loginValue, FILTER_VALIDATE_EMAIL);
        $localUser = $isEmail ? User::where('email', $loginValue)->first() : null;

        if ($localUser && !$localUser->isLdapUser()) {
            $credentials = [
                'email' => $loginValue,
                'password' => $password,
            ];

            if (Auth::attempt($credentials, $remember)) {
                $user = Auth::user();

                if (!$user->is_active) {
                    AuditService::logLoginFailed($loginValue, $request, 'Cuenta suspendida o desactivada');
                    Auth::logout();
                    $banMsg = $user->ban_reason
                        ? "Esta cuenta ha sido suspendida. Motivo: {$user->ban_reason}"
                        : 'Esta cuenta ha sido desactivada por la administración.';
                    return [
                        'success' => false,
                        'message' => $banMsg,
                    ];
                }

                RateLimiter::clear($throttleKey);

                $user->update([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                ]);

                AuditService::logLogin($user, $request, 'local');
                $this->telegramService->notifyUserLogin($user, $request->ip(), 'local');

                $request->session()->regenerate();
                return ['success' => true];
            }

            RateLimiter::hit($throttleKey, 60);
            AuditService::logLoginFailed($loginValue, $request, 'Credenciales locales incorrectas');

            return [
                'success' => false,
                'message' => 'Las credenciales de administrador local ingresadas no coinciden con nuestros registros.',
            ];
        }

        // 2. AUTENTICACIÓN LDAP (Cuentas corporativas)
        $ldapResult = $this->ldapService->authenticate($loginValue, $password);

        if (!$ldapResult['success']) {
            RateLimiter::hit($throttleKey, 60);
            AuditService::logLoginFailed($loginValue, $request, $ldapResult['message'] ?? 'Credenciales LDAP incorrectas');
            return [
                'success' => false,
                'message' => (string)($ldapResult['message'] ?? 'Credenciales LDAP incorrectas.'),
            ];
        }

        $ldapData = $ldapResult['data'];
        $cleanUsername = (string)$ldapData['username'];
        $cleanEmail = (string)$ldapData['email'];

        $user = User::where('username', $cleanUsername)
            ->orWhere('email', $cleanEmail)
            ->first();

        $isNewJitUser = false;
        if (!$user) {
            $isNewJitUser = true;
            $assignedRole = $ldapData['target_role'] ?? 'operator';
            $user = User::create([
                'name' => $ldapData['name'],
                'username' => $cleanUsername,
                'email' => $cleanEmail,
                'cedula' => !empty($ldapData['cedula']) ? $ldapData['cedula'] : null,
                'phone' => !empty($ldapData['phone']) ? $ldapData['phone'] : null,
                'personal_number' => !empty($ldapData['personal_number']) ? $ldapData['personal_number'] : null,
                'password' => bcrypt(Str::random(32)),
                'role' => $assignedRole,
                'is_active' => true,
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        } else {
            if (!$user->is_active) {
                AuditService::logLoginFailed($loginValue, $request, 'Cuenta LDAP suspendida o desactivada');
                $banMsg = $user->ban_reason
                    ? "Esta cuenta ha sido suspendida. Motivo: {$user->ban_reason}"
                    : 'Su cuenta se encuentra desactivada en este portal.';
                return [
                    'success' => false,
                    'message' => $banMsg,
                ];
            }

            $updateData = [
                'name' => $ldapData['name'],
                'username' => $cleanUsername,
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ];
            if (empty($user->cedula) && !empty($ldapData['cedula'])) {
                $updateData['cedula'] = $ldapData['cedula'];
            }
            if (empty($user->phone) && !empty($ldapData['phone'])) {
                $updateData['phone'] = $ldapData['phone'];
            }
            if (empty($user->personal_number) && !empty($ldapData['personal_number'])) {
                $updateData['personal_number'] = $ldapData['personal_number'];
            }
            $user->update($updateData);
        }

        try {
            $this->entityResolver->reconcileUserAssets($user);
        } catch (\Throwable $e) {
            Log::warning("Error conciliando activos para usuario {$user->id}: " . $e->getMessage());
        }

        Auth::login($user, $remember);
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        AuditService::logLogin($user, $request, 'ldap');

        if ($isNewJitUser) {
            $this->telegramService->notifyLdapAutoRegistered($user, $request->ip());
        } else {
            $this->telegramService->notifyUserLogin($user, $request->ip(), 'ldap');
        }

        return ['success' => true];
    }
}
