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

            $whitelistedIps = ['127.0.0.1', '::1'];
            $isLocalhost = in_array($ip, $whitelistedIps, true);

            // 1. BANEAR AL USUARIO
            $user->is_active = false;
            $user->ban_reason = $reason;
            $user->banned_at = $now;
            $user->save();

            // 2. BANEAR LA DIRECCIÓN IP (Únicamente si NO es localhost / loopback)
            if (!$isLocalhost && !BannedIp::isBanned($ip)) {
                BannedIp::create([
                    'ip_address' => $ip,
                    'reason' => $reason,
                    'user_id' => $user->id,
                    'banned_at' => $now,
                ]);
                $ipStatusTelegram = "• Dirección IP: <code>{$ip}</code> ⛔ <b>[BANEADA]</b>";
                $ipActionTelegram = "2. La dirección IP remota ha sido añadida a la lista negra del firewall.";
                $abortMsg = "Acceso Denegado: Su cuenta y su dirección IP ({$ip}) han sido baneadas por intentar manipular funciones administrativas no autorizadas.";
            } else {
                $ipStatusTelegram = "• Dirección IP: <code>{$ip}</code> 🛡️ <i>[IP Localhost Protegida - Exenta de Baneo]</i>";
                $ipActionTelegram = "2. La dirección IP ({$ip}) no fue baneada por ser interfaz de bucle local (Loopback).";
                $abortMsg = "Acceso Denegado: Su cuenta de usuario ha sido suspendida por intentar manipular funciones administrativas no autorizadas.";
            }

            // 3. REGISTRAR EN AUDITORÍA LOCAL
            $logEntry = sprintf(
                "[%s] 🚨 INTRUSIÓN Y BANEO AUTOMÁTICO | Usuario: %s (ID: %d, Email: %s, Rol: %s) | IP: %s (%s) | Ruta: %s [%s] | UserAgent: %s\n",
                $now->format('Y-m-d H:i:s'),
                $user->name,
                $user->id,
                $user->email,
                $user->role,
                $ip,
                $isLocalhost ? 'Localhost Exento' : 'IP Baneada',
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

            // 4. ENVIAR NOTIFICACIÓN INMEDIATA VÍA TELEGRAM AL OWNER
            try {
                $configPath = '/scripts/telegram-admin-bot/config/config.json';
                if (file_exists($configPath)) {
                    $botConfig = json_decode(file_get_contents($configPath), true);
                    $botToken = $botConfig['bot_token'] ?? null;
                    $ownerId = $botConfig['owner_id'] ?? null;

                    if ($botToken && $ownerId) {
                        $tgMessage = "🚨 <b>ALERTA DE SEGURIDAD • INTRUSIÓN DETECTADA</b>\n";
                        $tgMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
                        $tgMessage .= "⚠️ <i>Se ha detectado y bloqueado un intento no autorizado de manipulación en el portal web.</i>\n\n";
                        $tgMessage .= "👤 <b>Usuario:</b> " . htmlspecialchars($user->name) . " (ID: #" . $user->id . ")\n";
                        $tgMessage .= "📧 <b>Correo:</b> <code>" . htmlspecialchars($user->email) . "</code>\n";
                        $tgMessage .= "🏷️ <b>Rol:</b> <code>" . htmlspecialchars($user->role) . "</code>\n";
                        $tgMessage .= $ipStatusTelegram . "\n";
                        $tgMessage .= "⏰ <b>Fecha y Hora:</b> <code>" . $now->format('Y-m-d H:i:s') . "</code>\n";
                        $tgMessage .= "🎯 <b>Ruta Bloqueada:</b> [<code>" . htmlspecialchars($method) . "</code>] <code>" . htmlspecialchars($path) . "</code>\n";
                        $tgMessage .= "💻 <b>Navegador/SO:</b> <i>" . htmlspecialchars($userAgent) . "</i>\n\n";
                        $tgMessage .= "🔒 <b>Acciones automáticas aplicadas:</b>\n";
                        $tgMessage .= "1. Cuenta suspendida inmediatamente (<code>is_active = false</code>).\n";
                        $tgMessage .= $ipActionTelegram . "\n";
                        $tgMessage .= "3. Sesión activa cerrada y destruida.\n";
                        $tgMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
                        $tgMessage .= "🛠️ <i>Para gestionar o revertir el baneo, ingrese al módulo 'Baneos & Seguridad'.</i>";

                        \Illuminate\Support\Facades\Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $ownerId,
                            'text' => $tgMessage,
                            'parse_mode' => 'HTML',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Error enviando alerta de baneo a Telegram: " . $e->getMessage());
            }

            // 5. DESTRUIR LA SESIÓN DEL ATACANTE
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // 6. ABORTAR LA PETICIÓN
            abort(Response::HTTP_FORBIDDEN, $abortMsg);
        }

        return $next($request);
    }
}
