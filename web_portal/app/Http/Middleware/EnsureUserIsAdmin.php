<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(Response::HTTP_UNAUTHORIZED, 'No autenticado.');
        }

        // Si la cuenta ya estaba inactiva o baneada, cerrar de inmediato
        if (!$user->is_active) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }
            abort(Response::HTTP_FORBIDDEN, 'Su cuenta de usuario se encuentra suspendida.');
        }

        // Si el usuario NO es administrador -> ACCESO DENEGADO (Sin privilegios suficientes)
        if (!$user->isAdmin()) {
            $ip = $request->ip() ?: '0.0.0.0';
            $method = $request->method();
            $path = $request->path();
            $userAgent = $request->userAgent() ?: 'Desconocido';
            $now = now();

            // 1. REGISTRAR EN AUDITORÍA MARIADB SSOT (audit_logs)
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'event' => 'ACCESO_DENEGADO',
                    'module' => 'web_portal',
                    'description' => "Acceso administrativo denegado a la ruta: {$method} /{$path}",
                    'ip_address' => $ip,
                    'new_values' => [
                        'email' => $user->email,
                        'role' => $user->role,
                        'path' => $path,
                        'method' => $method,
                        'user_agent' => $userAgent,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning("Error registrando auditoría en MariaDB: " . $e->getMessage());
            }

            // 2. ABORTAR CON ERROR 403 FORBIDDEN (Manteniendo activa la sesión legítima del usuario)
            abort(Response::HTTP_FORBIDDEN, 'Acceso Denegado: Su cuenta de usuario no dispone de privilegios administrativos para realizar esta acción.');
        }

        return $next($request);
    }
}
