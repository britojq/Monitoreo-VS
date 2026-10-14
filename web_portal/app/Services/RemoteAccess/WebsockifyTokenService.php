<?php

declare(strict_types=1);

namespace App\Services\RemoteAccess;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Servicio Centralizado de Gestión de Tokens WebSockets (noVNC / Web-Telnet) y Auditoría SSOT.
 */
class WebsockifyTokenService
{
    protected string $tokensFile;
    protected int $maxTokens;

    public function __construct(?string $tokensFile = null, int $maxTokens = 100)
    {
        $this->tokensFile = $tokensFile ?? (string) config('services.websockify.tokens_file', '/etc/websockify/tokens.cfg');
        $this->maxTokens = $maxTokens ?: (int) config('services.websockify.max_tokens', 100);
    }

    /**
     * Obtener la ruta configurada para el archivo de tokens
     */
    public function getTokensFilePath(): string
    {
        return $this->tokensFile;
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
     * Registrar token en el archivo de configuración de websockify con bloqueo exclusivo
     */
    public function registerToken(string $token, string $ip, int $port): void
    {
        $dir = dirname($this->tokensFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $existingLines = [];
        if (file_exists($this->tokensFile)) {
            $rawLines = @file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($rawLines !== false) {
                // Podar el archivo conservando los últimos N registros
                $existingLines = array_slice($rawLines, -($this->maxTokens - 1));
            }
        }

        $newLine = "{$token}: {$ip}:{$port}";
        $existingLines[] = $newLine;

        $content = implode("\n", $existingLines) . "\n";
        $written = @file_put_contents($this->tokensFile, $content, LOCK_EX);

        if ($written === false) {
            Log::warning("[WebsockifyTokenService] No se pudo escribir en '{$this->tokensFile}'. Verifique permisos del servidor web.");
        }
    }

    /**
     * Verificar si el token existe y es válido
     */
    public function isTokenValid(string $token): bool
    {
        if (!file_exists($this->tokensFile)) {
            return false;
        }

        $lines = @file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, $token . ':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolver la IP y puerto destino a partir de un token registrado
     */
    public function resolveTarget(string $token): ?array
    {
        if (!file_exists($this->tokensFile)) {
            return null;
        }

        $lines = @file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

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

        return null;
    }

    /**
     * Revocar un token específico
     */
    public function revokeToken(string $token): bool
    {
        if (!file_exists($this->tokensFile)) {
            return false;
        }

        $lines = @file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        $remaining = [];
        $found = false;
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
