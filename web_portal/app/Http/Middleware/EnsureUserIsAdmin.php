<?php

namespace App\Http\Middleware;

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

            // 1. REGISTRAR EN AUDITORÍA LOCAL
            $logEntry = sprintf(
                "[%s] ⚠️ ACCESO DENEGADO (Sin privilegios administrativos) | Usuario: %s (ID: %d, Email: %s, Rol: %s) | IP: %s | Ruta: %s [%s] | UserAgent: %s\n",
                $now->format('Y-m-d H:i:s'),
                $user->name,
                $user->id,
                $user->email,
                $user->role,
                $ip,
                $path,
                $method,
                $userAgent
            );

            try {
                $auditPath = '/scripts/telegram-admin-bot/audit/intentos_acceso.log';
                file_put_contents($auditPath, $logEntry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                Log::warning("Error escribiendo en log de auditoría: " . $e->getMessage());
            }

            // 2. ABORTAR CON ERROR 403 FORBIDDEN (Manteniendo activa la sesión legítima del usuario)
            abort(Response::HTTP_FORBIDDEN, 'Acceso Denegado: Su cuenta de usuario no dispone de privilegios administrativos para realizar esta acción.');
        }

        return $next($request);
    }
}
