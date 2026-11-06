<?php

declare(strict_types=1);

namespace App\Services\RemoteAccess;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio Centralizado de Gestión de Tokens WebSockets (noVNC / Web-Telnet) y Auditoría SSOT.
 * Implementa gestión estricta de ciclo de vida con TTL (CWE-613 / CWE-598).
 */
class WebsockifyTokenService
{
    public const DEFAULT_TTL_SECONDS = 120;

    protected string $tokensFile;
    protected int $maxTokens;
    protected int $defaultTtl;

    public function __construct(?string $tokensFile = null, int $maxTokens = 100, int $defaultTtl = self::DEFAULT_TTL_SECONDS)
    {
        $this->tokensFile = $tokensFile ?? (string) config('services.websockify.tokens_file', '/etc/websockify/tokens.cfg');
        $this->maxTokens = $maxTokens ?: (int) config('services.websockify.max_tokens', 100);
        $this->defaultTtl = $defaultTtl > 0 ? $defaultTtl : self::DEFAULT_TTL_SECONDS;
    }

    /**
     * Obtener la ruta configurada para el archivo de tokens
     */
    public function getTokensFilePath(): string
    {
        return $this->tokensFile;
    }

    /**
     * Obtener la ruta del archivo de metadatos con timestamps de expiración
     */
    public function getMetaFilePath(): string
    {
        return $this->tokensFile . '.meta.json';
    }

    /**
     * Establecer dinámicamente la ruta de tokens (útil para pruebas unitarias)
     */
    public function setTokensFilePath(string $path): self
    {
        $this->tokensFile = $path;
        return $this;
    }

    /**
     * Generar un token aleatorio criptográficamente seguro
     */
    public function generateToken(string $prefix = 'vnc'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(16));
    }

    /**
     * Cargar metadatos de tokens activos
     */
    protected function loadMetadata(): array
    {
        $metaFile = $this->getMetaFilePath();
        if (!file_exists($metaFile)) {
            return [];
        }

        $raw = @file_get_contents($metaFile);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Guardar metadatos con bloqueo exclusivo
     */
    protected function saveMetadata(array $metadata): void
    {
        $metaFile = $this->getMetaFilePath();
        $dir = dirname($metaFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @file_put_contents($metaFile, $json ?: '{}', LOCK_EX);
    }

    /**
     * Purgar tokens expirados (CWE-613: Insufficient Session Expiration)
     */
    public function pruneExpiredTokens(): int
    {
        $now = time();
        $metadata = $this->loadMetadata();
        $validTokens = [];
        $prunedCount = 0;

        foreach ($metadata as $token => $info) {
            $expiresAt = (int) ($info['expires_at'] ?? 0);
            if ($expiresAt > $now) {
                $validTokens[$token] = $info;
            } else {
                $prunedCount++;
                Cache::forget("vnc_secret_{$token}");
            }
        }

        if ($prunedCount > 0 || !file_exists($this->tokensFile)) {
            $this->saveMetadata($validTokens);
            $this->syncTokensFile($validTokens);
        }

        return $prunedCount;
    }

    /**
     * Sincronizar archivo plano tokens.cfg para websockify
     */
    protected function syncTokensFile(array $validMetadata): void
    {
        $dir = dirname($this->tokensFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $lines = [];
        foreach ($validMetadata as $token => $info) {
            $ip = $info['ip'] ?? '';
            $port = $info['port'] ?? '';
            if ($ip !== '' && $port !== '') {
                $lines[] = "{$token}: {$ip}:{$port}";
            }
        }

        // Limitar a los últimos maxTokens
        if (count($lines) > $this->maxTokens) {
            $lines = array_slice($lines, -$this->maxTokens);
        }

        $content = empty($lines) ? '' : implode("\n", $lines) . "\n";
        @file_put_contents($this->tokensFile, $content, LOCK_EX);
    }

    /**
     * Registrar token con tiempo de vida (TTL) estricto (SEC-09)
     */
    public function registerToken(string $token, string $ip, int $port, ?int $ttl = null): void
    {
        $ttlSeconds = ($ttl !== null && $ttl > 0) ? $ttl : $this->defaultTtl;
        $now = time();

        // 1. Purgar previamente los tokens ya expirados
        $metadata = $this->loadMetadata();
        $activeMetadata = [];

        foreach ($metadata as $t => $info) {
            if ((int)($info['expires_at'] ?? 0) > $now) {
                $activeMetadata[$t] = $info;
            } else {
                Cache::forget("vnc_secret_{$t}");
            }
        }

        // 2. Registrar nuevo token con timestamp de caducidad
        $activeMetadata[$token] = [
            'ip' => $ip,
            'port' => $port,
            'created_at' => $now,
            'expires_at' => $now + $ttlSeconds,
            'ttl' => $ttlSeconds,
        ];

        // 3. Limitar tamaño máximo
        if (count($activeMetadata) > $this->maxTokens) {
            $activeMetadata = array_slice($activeMetadata, -$this->maxTokens, null, true);
        }

        $this->saveMetadata($activeMetadata);
        $this->syncTokensFile($activeMetadata);
    }

    /**
     * Registrar contraseña o secreto de sesión de forma segura en memoria backend temporal (SEC-03)
     */
    public function registerSessionSecret(string $token, string $secret, ?int $ttl = null): void
    {
        $ttlSeconds = ($ttl !== null && $ttl > 0) ? $ttl : $this->defaultTtl;
        Cache::put("vnc_secret_{$token}", $secret, now()->addSeconds($ttlSeconds));
    }

    /**
     * Obtener el secreto de sesión efímero
     */
    public function getSessionSecret(string $token): ?string
    {
        if (!$this->isTokenValid($token)) {
            return null;
        }

        $secret = Cache::get("vnc_secret_{$token}");
        return is_string($secret) ? $secret : null;
    }

    /**
     * Verificar si el token existe y NO ha expirado (CWE-613)
     */
    public function isTokenValid(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $metadata = $this->loadMetadata();
        if (isset($metadata[$token])) {
            $expiresAt = (int) ($metadata[$token]['expires_at'] ?? 0);
            if ($expiresAt <= time()) {
                // Token expirado
                return false;
            }
            return true;
        }

        // Fallback: Si no hay metadata json pero existe en archivo plano
        if (file_exists($this->tokensFile)) {
            $lines = @file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, $token . ':')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Resolver la IP y puerto destino a partir de un token registrado vigente
     */
    public function resolveTarget(string $token): ?array
    {
        if (!$this->isTokenValid($token)) {
            return null;
        }

        $metadata = $this->loadMetadata();
        if (isset($metadata[$token])) {
            return [
                'ip' => $metadata[$token]['ip'] ?? null,
                'port' => isset($metadata[$token]['port']) ? (int) $metadata[$token]['port'] : null,
            ];
        }

        if (file_exists($this->tokensFile)) {
            $lines = @file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, $token . ':')) {
                        $target = trim(substr($line, strlen($token) + 1));
                        $parts = explode(':', $target);
                        return [
                            'ip' => $parts[0] ?? null,
                            'port' => isset($parts[1]) ? (int) $parts[1] : null,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Revocar un token específico inmediatamente
     */
    public function revokeToken(string $token): bool
    {
        $metadata = $this->loadMetadata();
        $found = false;

        if (isset($metadata[$token])) {
            unset($metadata[$token]);
            $this->saveMetadata($metadata);
            $this->syncTokensFile($metadata);
            $found = true;
        }

        Cache::forget("vnc_secret_{$token}");

        if (!$found && file_exists($this->tokensFile)) {
            $lines = @file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $remaining = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, $token . ':')) {
                        $found = true;
                        continue;
                    }
                    $remaining[] = $line;
                }
                if ($found) {
                    $content = implode("\n", $remaining) . (empty($remaining) ? "" : "\n");
                    @file_put_contents($this->tokensFile, $content, LOCK_EX);
                }
            }
        }

        return $found;
    }

    /**
     * Registrar auditoría en MariaDB SSOT para una sesión remota iniciada
     */
    public function auditSession(
        string $protocol,
        User $user,
        string $targetIp,
        int $targetPort,
        string $targetName,
        string $targetSite,
        ?string $token = null,
        ?Request $request = null
    ): AuditLog {
        $protocolUpper = strtoupper($protocol);
        $event = strtolower($protocol) . '_session';

        $entityName = match(strtolower($protocol)) {
            'vnc' => 'Escritorio Remoto VNC',
            'telnet' => 'Terminal Remota Telnet',
            'ssh' => 'Consola Web SSH',
            default => 'Sesión Remota',
        };

        $asset = InventoryAsset::where('ip_address', $targetIp)->first();

        // Registro de bajo nivel en syslog/laravel.log
        Log::info(sprintf(
            '[%s] Sesión web iniciada por %s (%s) para equipo %s (%s:%d) en %s%s',
            $protocolUpper,
            $user->name,
            $user->role,
            $targetName,
            $targetIp,
            $targetPort,
            $targetSite,
            $token ? " [Token: {$token}]" : ''
        ));

        // Registro en MariaDB audit_logs (SSOT)
        return AuditService::logCustom(
            event: $event,
            module: 'remote',
            entityName: $entityName,
            entityLabel: "{$targetName} ({$targetIp}:{$targetPort})",
            description: "Sesión {$protocolUpper} web iniciada por [{$user->name}] hacia {$targetName} ({$targetIp}:{$targetPort}) en {$targetSite}",
            auditable: $asset,
            newValues: array_filter([
                'protocol' => $protocolUpper,
                'target_name' => $targetName,
                'target_ip' => $targetIp,
                'target_port' => $targetPort,
                'target_site' => $targetSite,
                'token' => $token,
            ]),
            request: $request,
            user: $user
        );
    }
}
