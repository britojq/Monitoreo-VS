<?php

namespace App\Http\Middleware;

use App\Models\BannedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            $request->session()->invalidate();
            abort(Response::HTTP_FORBIDDEN, 'Su cuenta de usuario se encuentra suspendida.');
        }

        // Si el usuario NO es administrador -> VIOLACIÓN DE PRIVILEGIOS / ACCIÓN INTRUSIVA
        if (!$user->isAdmin()) {
            $ip = $request->ip() ?: '0.0.0.0';
            $method = $request->method();
            $path = $request->path();
            $userAgent = $request->userAgent() ?: 'Desconocido';
            $now = now();
            $reason = "Intento no autorizado de manipulación en: [{$method}] {$path}";

            // 1. BANEAR AL USUARIO
            $user->is_active = false;
            $user->ban_reason = $reason;
            $user->banned_at = $now;
            $user->save();

            // 2. BANEAR LA DIRECCIÓN IP
            if (!BannedIp::isBanned($ip)) {
                BannedIp::create([
                    'ip_address' => $ip,
                    'reason' => $reason,
                    'user_id' => $user->id,
                    'banned_at' => $now,
                ]);
            }

            // 3. REGISTRAR EN AUDITORÍA LOCAL
            $logEntry = sprintf(
                "[%s] 🚨 INTRUSIÓN Y BANEO AUTOMÁTICO | Usuario: %s (ID: %d, Email: %s, Rol: %s) | IP: %s | Ruta: %s [%s] | UserAgent: %s\n",
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
                Log::error("Error escribiendo en log de auditoría: " . $e->getMessage());
            }

            // 4. ENVIAR CORREO DE ALERTA AL ADMINISTRADOR
            try {
                $emailBody = "🚨 ALERTA DE SEGURIDAD - BANEO AUTOMÁTICO DE USUARIO E IP\n";
                $emailBody .= "========================================================\n\n";
                $emailBody .= "Se ha detectado y bloqueado un intento no autorizado de manipulación del sistema.\n\n";
                $emailBody .= "DATOS DEL INCIDENTE:\n";
                $emailBody .= "• Fecha y Hora: " . $now->format('Y-m-d H:i:s') . "\n";
                $emailBody .= "• Dirección IP: " . $ip . " [BANEADA]\n";
                $emailBody .= "• Usuario Infractor: " . $user->name . " (ID: " . $user->id . ") [BANEADO]\n";
                $emailBody .= "• Correo del Usuario: " . $user->email . "\n";
                $emailBody .= "• Rol del Usuario: " . $user->role . "\n";
                $emailBody .= "• Endpoint Intentado: " . $path . " (" . $method . ")\n";
                $emailBody .= "• Navegador / Agente: " . $userAgent . "\n\n";
                $emailBody .= "ACCIONES AUTOMÁTICAS APLICADAS:\n";
                $emailBody .= "1. La cuenta de usuario ha sido suspendida inmediatamente (is_active = false).\n";
                $emailBody .= "2. La dirección IP ha sido añadida a la lista negra del firewall de la aplicación.\n";
                $emailBody .= "3. La sesión activa ha sido invalidada y cerrada en el acto.\n\n";
                $emailBody .= "Para gestionar o remover el baneo, ingrese como Administrador al módulo 'Baneos & Seguridad'.\n";

                Mail::raw($emailBody, function ($message) use ($user, $ip) {
                    $message->to('britojq@gmail.com')
                            ->subject("🚨 [ALERTA] Intento de Violación de Seguridad - Usuario {$user->name} e IP {$ip} BANEADOS");
                });
            } catch (\Throwable $e) {
                Log::error("Error enviando correo de alerta de baneo: " . $e->getMessage());
            }

            // 5. DESTRUIR LA SESIÓN DEL ATACANTE
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // 6. ABORTAR LA PETICIÓN
            abort(Response::HTTP_FORBIDDEN, 'Acceso Denegado: Su cuenta y su dirección IP (' . $ip . ') han sido baneadas por intentar manipular funciones administrativas no autorizadas.');
        }

        return $next($request);
    }
}
