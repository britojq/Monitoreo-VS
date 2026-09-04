<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        // Acepta tanto 'login' como 'email' para máxima compatibilidad
        $loginValue = trim($request->input('login', $request->input('email', '')));
        $password = $request->input('password', '');
        $remember = $request->boolean('remember');

        $request->merge(['login' => $loginValue]);

        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'Debe ingresar su usuario LDAP o correo.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $throttleKey = 'login-attempt:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'login' => "Demasiados intentos fallidos. Por favor espere {$seconds} segundos antes de reintentar.",
            ])->onlyInput('login');
        }

        // =========================================================================
        // CASO 1: ADMINISTRADOR (Autenticación Local Failsafe por Correo Electrónico)
        // =========================================================================
        if (filter_var($loginValue, FILTER_VALIDATE_EMAIL)) {
            $credentials = [
                'email' => $loginValue,
                'password' => $password,
            ];

            if (Auth::attempt($credentials, $remember)) {
                $user = Auth::user();

                if (!$user->is_active) {
                    Auth::logout();
                    $banMsg = $user->ban_reason 
                        ? "Esta cuenta ha sido suspendida. Motivo: {$user->ban_reason}"
                        : 'Esta cuenta ha sido desactivada por la administración.';
                    return back()->withErrors(['login' => $banMsg])->onlyInput('login');
                }

                RateLimiter::clear($throttleKey);

                $user->update([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                ]);

                // Notificar acceso a Telegram
                /** @var \App\Services\TelegramNotificationService $telegramService */
                $telegramService = app(\App\Services\TelegramNotificationService::class);
                $telegramService->notifyUserLogin($user, $request->ip(), 'local');

                $request->session()->regenerate();
                return redirect()->intended(route('admin.dashboard'));
            }

            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'login' => 'Las credenciales de administrador ingresadas no coinciden con nuestros registros locales.',
            ])->onlyInput('login');
        }

        // =========================================================================
        // CASO 2: OPERADORES / PERSONAL CORPORATIVO (Autenticación LDAP & Auto-Registro)
        // =========================================================================
        /** @var \App\Services\LdapAuthService $ldapService */
        $ldapService = app(\App\Services\LdapAuthService::class);
        $ldapResult = $ldapService->authenticate($loginValue, $password);

        if (!$ldapResult['success']) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors([
                'login' => $ldapResult['message'],
            ])->onlyInput('login');
        }

        // Si la autenticación LDAP fue exitosa, procedemos con el aprovisionamiento JIT
        $ldapData = $ldapResult['data'];
        $cleanUsername = $ldapData['username'];
        $cleanEmail = $ldapData['email'];

        // Buscar si el usuario ya existe por username o por email
        $user = \App\Models\User::where('username', $cleanUsername)
            ->orWhere('email', $cleanEmail)
            ->first();

        $isNewJitUser = false;
        if (!$user) {
            $isNewJitUser = true;
            // AUTO-REGISTRO JIT (Just-In-Time) como Operador
            $user = \App\Models\User::create([
                'name' => $ldapData['name'],
                'username' => $cleanUsername,
                'email' => $cleanEmail,
                'password' => bcrypt(\Illuminate\Support\Str::random(32)), // Clave local aleatoria segura
                'role' => 'operator', // Rol predeterminado para cuentas LDAP
                'is_active' => true,
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        } else {
            // Si ya existía, validar estado activo
            if (!$user->is_active) {
                $banMsg = $user->ban_reason 
                    ? "Esta cuenta ha sido suspendida. Motivo: {$user->ban_reason}"
                    : 'Su cuenta se encuentra desactivada en este portal.';
                return back()->withErrors(['login' => $banMsg])->onlyInput('login');
            }

            // Sincronizar datos actualizados desde LDAP
            $user->update([
                'name' => $ldapData['name'],
                'username' => $cleanUsername,
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        }

        // Iniciar sesión en el portal
        Auth::login($user, $remember);
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Notificar a Telegram
        /** @var \App\Services\TelegramNotificationService $telegramService */
        $telegramService = app(\App\Services\TelegramNotificationService::class);
        if ($isNewJitUser) {
            $telegramService->notifyLdapAutoRegistered($user, $request->ip());
        } else {
            $telegramService->notifyUserLogin($user, $request->ip(), 'ldap');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
