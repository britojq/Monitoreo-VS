<?php

namespace App\Services\Notification;

use App\Contracts\NotifierInterface;
use App\Models\BotSetting;
use App\Models\MonitoredProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de infraestructura de transporte de mensajería vía Telegram Bot API.
 * Responsabilidad Única (S): Conectividad HTTP con Telegram, failover por proxies y respeto a la Regla de Oro #1.
 */
class TelegramTransport implements NotifierInterface
{
    protected ?string $botToken = null;
    protected array $targetChats = [];
    protected array $proxies = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Cargar token y destinatarios permitidos directamente desde MariaDB (SSOT).
     * REGLA DE ORO #1: Prohibición absoluta de enviar mensajes al grupo general.
     */
    protected function loadConfig(): void
    {
        $dbToken = (string) (BotSetting::get('bot_token') ?: '');
        $dbOwnerId = (string) (BotSetting::get('owner_id') ?: '38914901');

        if (!empty($dbToken) && $dbToken !== '[ENCRYPTED_HARDWARE_VAULT]') {
            $this->botToken = $dbToken;
        } else {
            $this->botToken = \App\Services\Security\HardwareVaultService::resolveBotToken();
        }

        if (!empty($dbOwnerId)) {
            $this->targetChats[] = $dbOwnerId;
        }

        // Contingencia transparente si no se ha resuelto el token ni el destinatario
        if (empty($this->botToken)) {
            $this->botToken = \App\Services\Security\HardwareVaultService::resolveBotToken();
        }
        if (empty($this->targetChats)) {
            $this->targetChats[] = '38914901';
        }

        // Cargar proxies activos desde MariaDB centralizada
        try {
            $dbProxies = MonitoredProxy::where('is_active', true)->orderBy('letter')->get();
            foreach ($dbProxies as $p) {
                $ip = trim($p->ip_port ?? '');
                $auth = trim($p->auth_userpass ?? '');
                if (!empty($ip)) {
                    if (!empty($auth) && str_contains($auth, ':') && $auth !== 'USUARIO:CLAVE') {
                        [$u, $pwd] = explode(':', $auth, 2);
                        $proxyUrl = 'http://' . urlencode($u) . ':' . urlencode($pwd) . '@' . $ip;
                    } else {
                        $proxyUrl = 'http://' . $ip;
                    }
                    $this->proxies[] = $proxyUrl;
                }
            }
        } catch (\Throwable $e) {
            Log::debug("No se pudieron cargar proxies de MariaDB en TelegramTransport: " . $e->getMessage());
        }

        // FILTRO DE SEGURIDAD ABSOLUTO: Excluir explícitamente cualquier Chat ID que empiece con "-"
        $this->targetChats = array_values(array_filter(array_unique($this->targetChats), function ($chatId) {
            return !str_starts_with((string)$chatId, '-');
        }));

        // Garantizar al menos el ID privado del Administrador / Owner (38914901)
        if (empty($this->targetChats)) {
            $this->targetChats[] = '38914901';
        }
    }

    /**
     * Envía un mensaje a un destinatario específico (implementación de NotifierInterface).
     */
    public function send(string $recipient, string $message, string $format = 'HTML'): bool
    {
        // REGLA DE ORO #1: Si el destinatario es un grupo (ID negativo), abortar inmediatamente
        if (str_starts_with(trim($recipient), '-')) {
            Log::warning("TelegramTransport: Intento de envío a chat de grupo bloqueado ({$recipient}).");
            return false;
        }

        // En entorno de pruebas automatizadas silenciar envíos reales para evitar spam o fallos de red
        if (app()->environment('testing')) {
            Log::debug('TelegramTransport: Envío omitido en entorno de pruebas (testing).');
            return true;
        }

        if (empty($this->botToken)) {
            Log::warning('TelegramTransport: Bot token no configurado.');
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        return $this->sendToChat($url, $recipient, $message, $format);
    }

    /**
     * Enviar mensaje a todos los chats de administración configurados (Owner privado).
     */
    public function sendToDefaultChats(string $message, string $format = 'HTML'): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        if (empty($this->botToken) || empty($this->targetChats)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $successCount = 0;

        foreach ($this->targetChats as $chatId) {
            if ($this->send((string)$chatId, $message, $format)) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }

    /**
     * Envío a un chat individual con failover directo y a través de proxies MariaDB.
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
            Log::debug("Telegram direct send failed for chat {$chatId}: " . ($response->body() ?: 'HTTP ' . $response->status()));
        } catch (\Throwable $e) {
            Log::debug("Telegram direct send exception for chat {$chatId}: " . $e->getMessage());
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

    public function getTargetChats(): array
    {
        return $this->targetChats;
    }

    public function getBotToken(): ?string
    {
        return $this->botToken;
    }
}
