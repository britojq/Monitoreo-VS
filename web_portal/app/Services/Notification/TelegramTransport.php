<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Contracts\NotifierInterface;
use App\Contracts\Services\BotTokenResolverInterface;
use App\Models\MonitoredProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de infraestructura de transporte de mensajería vía Telegram Bot API.
 * 
 * Principios SOLID y Reglas Inviolables:
 * - Responsabilidad Única (SRP): Conectividad HTTP con Telegram y failover por proxies.
 * - Inversión de Dependencias (DIP): Inyecta BotTokenResolverInterface.
 * - Regla de Oro #1: Prohibición absoluta de enviar mensajes a grupos corporativos (IDs negativos).
 * - CERO despacho hacia bots centinelas de DRM/licencias.
 */
class TelegramTransport implements NotifierInterface
{
    protected ?string $botToken = null;
    /** @var array<int, string> */
    protected array $targetChats = [];
    /** @var array<int, string> */
    protected array $proxies = [];

    public function __construct(
        private readonly ?BotTokenResolverInterface $tokenResolver = null
    ) {
        $this->loadConfig();
    }

    /**
     * Cargar token y destinatarios permitidos a través del resolver inyectado.
     * REGLA DE ORO #1: Prohibición absoluta de enviar mensajes a grupos.
     */
    protected function loadConfig(): void
    {
        $resolver = $this->tokenResolver ?? (app()->bound(BotTokenResolverInterface::class) ? app(BotTokenResolverInterface::class) : null);

        if ($resolver !== null) {
            try {
                $this->botToken = $resolver->resolve();
            } catch (\Throwable $e) {
                Log::warning('TelegramTransport: No se pudo resolver botToken desde BotTokenResolverInterface: ' . $e->getMessage());
                $this->botToken = null;
            }

            $ownerId = (string) $resolver->getOwnerId();
            if ($ownerId !== '' && !str_starts_with($ownerId, '-')) {
                $this->targetChats[] = $ownerId;
            }
        }

        // Cargar proxies activos desde MariaDB centralizada
        try {
            $dbProxies = MonitoredProxy::where('is_active', true)->orderBy('letter')->get();
            foreach ($dbProxies as $p) {
                $ip = trim((string) ($p->ip_port ?? ''));
                $auth = trim((string) ($p->auth_userpass ?? ''));
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
            Log::debug('No se pudieron cargar proxies de MariaDB en TelegramTransport: ' . $e->getMessage());
        }

        // FILTRO DE SEGURIDAD ABSOLUTO: Excluir explícitamente cualquier Chat ID de grupo (que empiece con "-")
        $this->targetChats = array_values(array_filter(array_unique($this->targetChats), function (string $chatId): bool {
            return !str_starts_with(trim($chatId), '-');
        }));

        // Garantizar al menos el ID privado oficial del Administrador / Owner (<OWNER_TELEGRAM_ID>)
        if (empty($this->targetChats)) {
            $this->targetChats[] = '<OWNER_TELEGRAM_ID>';
        }
    }

    /**
     * Envía un mensaje a un destinatario específico (implementación de NotifierInterface).
     */
    public function send(string $recipient, string $message, string $format = 'HTML'): bool
    {
        // REGLA DE ORO #1: Si el destinatario es un grupo (ID negativo), anular inmediatamente
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
            Log::warning('TelegramTransport: Bot token no resuelto.');
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
            if ($this->send((string) $chatId, $message, $format)) {
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
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getTargetChats(): array
    {
        return $this->targetChats;
    }

    public function getBotToken(): ?string
    {
        return $this->botToken;
    }
}
