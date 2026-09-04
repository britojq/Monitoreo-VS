<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    protected ?string $botToken = null;
    protected array $targetChats = [];
    protected array $proxies = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Cargar token y destinatario ÚNICAMENTE del Propietario / Administrador Privado.
     * REGLA ESTRICTA: JAMÁS enviar al grupo general bajo ninguna circunstancia.
     */
    protected function loadConfig(): void
    {
        $jsonPath = '/scripts/telegram-admin-bot/config/config.json';
        $botConfPath = '/scripts/telegram-admin-bot/config/bot.conf';

        // 1. Cargar exclusivamente el Owner ID privado desde config.json
        if (file_exists($jsonPath)) {
            $json = json_decode(@file_get_contents($jsonPath), true);
            if (is_array($json)) {
                if (!empty($json['bot_token'])) {
                    $this->botToken = $json['bot_token'];
                }
                if (!empty($json['owner_id'])) {
                    $this->targetChats[] = (string)$json['owner_id'];
                }
            }
        }

        // 2. Cargar desde bot.conf (IDC privado y proxies)
        if (file_exists($botConfPath)) {
            $confContent = @file_get_contents($botConfPath);
            if ($confContent) {
                $lines = explode("\n", $confContent);
                $conf = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim($v, " \t\n\r\0\x0B\"'");
                        $conf[$k] = $v;
                    }
                }

                if (empty($this->botToken) && !empty($conf['TOKENA'])) {
                    $this->botToken = $conf['TOKENA'];
                }

                // IDC es el ID del propietario en bot.conf
                if (!empty($conf['IDC']) && !in_array((string)$conf['IDC'], $this->targetChats)) {
                    $this->targetChats[] = (string)$conf['IDC'];
                }

                // Cargar proxies
                foreach (['A', 'B', 'C', 'D'] as $letter) {
                    $ip = $conf["IPADDRPORTPROXY{$letter}"] ?? null;
                    $auth = $conf["USERPASSWDPROXY{$letter}"] ?? null;
                    if ($ip) {
                        if ($auth && str_contains($auth, ':')) {
                            [$u, $p] = explode(':', $auth, 2);
                            $proxyUrl = 'http://' . urlencode($u) . ':' . urlencode($p) . '@' . $ip;
                        } else {
                            $proxyUrl = 'http://' . $ip;
                        }
                        $this->proxies[] = $proxyUrl;
                    }
                }
            }
        }

        // FILTRO DE SEGURIDAD ABSOLUTO: Excluir explícitamente cualquier Chat ID que empiece con "-" (grupos/supergrupos)
        $this->targetChats = array_values(array_filter(array_unique($this->targetChats), function ($chatId) {
            return !str_starts_with((string)$chatId, '-');
        }));
    }

    /**
     * Enviar mensaje HTML formateado únicamente a chats privados de administración
     */
    public function sendMessage(string $text, string $parseMode = 'HTML'): bool
    {
        if (empty($this->botToken) || empty($this->targetChats)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $successCount = 0;

        foreach ($this->targetChats as $chatId) {
            // Seguridad redundante: si por algún motivo llega un ID de grupo, abortar ese envío
            if (str_starts_with((string)$chatId, '-')) {
                continue;
            }

            if ($this->sendToChat($url, $chatId, $text, $parseMode)) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }

    /**
     * Envío a un chat individual con failover a proxies
     */
    protected function sendToChat(string $url, string $chatId, string $text, string $parseMode): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        // 1. Probar conexión directa
        try {
            $response = Http::timeout(4)->asForm()->post($url, $payload);
            if ($response->successful() && ($response->json('ok') === true)) {
                return true;
            }
        } catch (\Throwable $e) {
            Log::debug("Telegram direct send failed for chat {$chatId}: " . $e->getMessage());
        }

        // 2. Probar mediante proxies configurados
        foreach ($this->proxies as $proxyUrl) {
            try {
                $response = Http::withOptions(['proxy' => $proxyUrl])
                    ->timeout(5)
                    ->asForm()
                    ->post($url, $payload);

                if ($response->successful() && ($response->json('ok') === true)) {
                    return true;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return false;
    }

    /**
     * Notificación cuando un usuario se registra automáticamente vía LDAP (JIT)
     */
    public function notifyLdapAutoRegistered(User $user, string $ip): void
    {
        rescue(function () use ($user, $ip) {
            $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
            $msg = "🆕 <b>NUEVO USUARIO REGISTRADO VÍA LDAP</b>\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "👤 <b>Nombre:</b> " . htmlspecialchars($user->name) . "\n"
                 . "🆔 <b>Usuario:</b> <code>" . htmlspecialchars($user->username ?? 'N/A') . "</code>\n"
                 . "📧 <b>Email:</b> " . htmlspecialchars($user->email) . "\n"
                 . "🏷️ <b>Rol Asignado:</b> " . strtoupper($user->role) . "\n"
                 . "🌐 <b>IP de Conexión:</b> <code>{$ip}</code>\n"
                 . "📅 <b>Fecha y Hora:</b> {$now}\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "ℹ️ <i>Registrado automáticamente al autenticar por primera vez con el Directorio Activo LDAP.</i>";

            // Guardar siempre en auditoría
            $this->logAudit("AUTO-REGISTRO LDAP", $user, $ip, "Auto-registro exitoso como {$user->role}");

            // Guardia de seguridad: no despachar a Telegram si proviene de loopback de pruebas (127.0.0.1)
            if ($ip === '127.0.0.1' || $ip === '::1') {
                return;
            }

            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Notificación cuando un usuario accede al sistema con credenciales asignadas
     */
    public function notifyUserLogin(User $user, string $ip, string $authType = 'local'): void
    {
        rescue(function () use ($user, $ip, $authType) {
            $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
            $isLdap = ($authType === 'ldap' || $user->isLdapUser());

            $title = $isLdap ? "🔑 <b>INICIO DE SESIÓN (USUARIO LDAP)</b>" : "🔐 <b>INICIO DE SESIÓN (USUARIO LOCAL)</b>";
            $methodDesc = $isLdap 
                ? "Acceso mediante credenciales corporativas LDAP autorizadas."
                : "Acceso mediante credenciales locales asignadas por el Administrador.";

            $msg = "{$title}\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "👤 <b>Nombre:</b> " . htmlspecialchars($user->name) . "\n";

            if (!empty($user->username)) {
                $msg .= "🆔 <b>Usuario LDAP:</b> <code>" . htmlspecialchars($user->username) . "</code>\n";
            }

            $msg .= "📧 <b>Email:</b> <code>" . htmlspecialchars($user->email) . "</code>\n"
                 . "🏷️ <b>Rol:</b> " . strtoupper($user->role) . "\n"
                 . "🌐 <b>IP de Conexión:</b> <code>{$ip}</code>\n"
                 . "📅 <b>Fecha y Hora:</b> {$now}\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "ℹ️ <i>{$methodDesc}</i>";

            // Guardar siempre en auditoría
            $this->logAudit("INICIO DE SESIÓN", $user, $ip, "Login exitoso ({$authType})");

            // Guardia de seguridad: no despachar a Telegram si proviene de loopback de pruebas (127.0.0.1)
            if ($ip === '127.0.0.1' || $ip === '::1') {
                return;
            }

            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Notificación cuando un administrador otorga o autoriza acceso a un nuevo usuario
     */
    public function notifyUserCreatedOrAuthorized(User $user, string $adminName, string $origin = 'manual'): void
    {
        rescue(function () use ($user, $adminName, $origin) {
            $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
            $title = ($origin === 'ldap_preauth') 
                ? "👤 <b>ACCESO CONCEDIDO: USUARIO LDAP PRE-AUTORIZADO</b>" 
                : "👤 <b>ACCESO CONCEDIDO: NUEVO USUARIO LOCAL</b>";

            $msg = "{$title}\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "🛡️ <b>Otorgado por Administrador:</b> " . htmlspecialchars($adminName) . "\n"
                 . "👤 <b>Nombre:</b> " . htmlspecialchars($user->name) . "\n";

            if (!empty($user->username)) {
                $msg .= "🆔 <b>Usuario LDAP:</b> <code>" . htmlspecialchars($user->username) . "</code>\n";
            }

            $msg .= "📧 <b>Email:</b> " . htmlspecialchars($user->email) . "\n"
                 . "🏷️ <b>Rol Asignado:</b> " . strtoupper($user->role) . "\n"
                 . "📅 <b>Fecha y Hora:</b> {$now}\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "ℹ️ <i>El usuario ya puede ingresar al portal utilizando sus credenciales asignadas.</i>";

            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Escribir línea en el archivo de auditoría local
     */
    protected function logAudit(string $action, User $user, string $ip, string $detail): void
    {
        $logPath = '/scripts/telegram-admin-bot/audit/intentos_acceso.log';
        $now = now()->format('Y-m-d H:i:s');
        $idStr = $user->username ? "UID: {$user->username}" : "ID: {$user->id}";
        $line = sprintf(
            "[%s] 🌐 %s | %s (%s, %s, Rol: %s) | IP: %s | %s\n",
            $now,
            $action,
            $user->name,
            $idStr,
            $user->email,
            $user->role,
            $ip,
            $detail
        );
        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
