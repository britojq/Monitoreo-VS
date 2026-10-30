<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Contracts\Services\HardwareVaultReaderInterface;
use App\Services\System\SonarPathService;
use Illuminate\Support\Facades\Log;

/**
 * Servicio Criptográfico de Lectura de Bóveda Anclada al Hardware (Hardware-Bound Vault).
 * 
 * En estricto cumplimiento de la Regla de Oro #13:
 * - El token operacional reside cifrado en system/vault/.sys_anchor.
 * - Desencriptado exclusivo en RAM volátil mediante derivación PBKDF2 de componentes físicos del host.
 * - CERO referencias o fallbacks hacia bots centinelas de DRM/licencias.
 */
class HardwareVaultReaderService implements HardwareVaultReaderInterface
{
    private const SALT = 'SALT_ATIT_VALLE_SECO_2026';
    private const ITERATIONS = 10000;
    private const KEY_LENGTH = 32;

    /**
     * @return array<int, string>
     */
    private function getCandidatePaths(): array
    {
        return [
            SonarPathService::vaultPath('.sys_anchor'),
            base_path('../system/vault/.sys_anchor'),
            base_path('system/vault/.sys_anchor'),
            '/opt/sonar/system/vault/.sys_anchor',
        ];
    }

    /**
     * Resuelve la ruta canónica legible de la bóveda anclada al hardware.
     */
    public function resolveAnchorPath(): ?string
    {
        foreach ($this->getCandidatePaths() as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    public function isAvailable(): bool
    {
        return $this->resolveAnchorPath() !== null;
    }

    public function unwrap(): ?string
    {
        $anchorFile = $this->resolveAnchorPath();
        if ($anchorFile === null) {
            $currentUser = posix_getpwuid(posix_geteuid())['name'] ?? 'unknown';
            Log::warning("HardwareVaultReaderService: No se encontró .sys_anchor legible para el usuario '{$currentUser}'.");
            return null;
        }

        try {
            $rawJson = @file_get_contents($anchorFile);
            if (!$rawJson) {
                Log::warning("HardwareVaultReaderService: Archivo .sys_anchor vacío o ilegible en {$anchorFile}.");
                return null;
            }

            /** @var array<string, mixed>|null $data */
            $data = json_decode($rawJson, true);
            if (!is_array($data) || empty($data['tb']) || empty($data['ts'])) {
                Log::warning("HardwareVaultReaderService: Estructura JSON corrupta en {$anchorFile}.");
                return null;
            }

            $hwKey = $this->deriveHardwareKey();
            $rawTb = base64_decode((string) $data['tb'], true);
            if ($rawTb === false) {
                Log::warning("HardwareVaultReaderService: Payload base64 inválido en {$anchorFile}.");
                return null;
            }

            $computedSig = hash_hmac('sha256', $rawTb, $hwKey);
            if (!hash_equals($computedSig, (string) $data['ts'])) {
                Log::error('HardwareVaultReaderService: Firma HMAC inválida. Componentes de hardware no coinciden.');
                return null;
            }

            $tok = '';
            $keyLen = strlen($hwKey);
            $tbLen = strlen($rawTb);
            for ($i = 0; $i < $tbLen; $i++) {
                $b = ord($rawTb[$i]);
                $k = ord($hwKey[$i % $keyLen]);
                $unrotated = (($b >> 3) | (($b << 5) & 0xFF)) & 0xFF;
                $tok .= chr($unrotated ^ $k);
            }

            $cleanToken = trim($tok);
            if (str_contains($cleanToken, ':') && strlen($cleanToken) >= 30) {
                return $cleanToken;
            }

            Log::error('HardwareVaultReaderService: Token desencriptado no cumple con formato Telegram (ID:HASH).');
            return null;
        } catch (\Throwable $e) {
            Log::error('HardwareVaultReaderService: Error desencriptando bóveda: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Deriva la clave criptográfica a partir de los componentes físicos inmutables del servidor.
     */
    private function deriveHardwareKey(): string
    {
        $components = [];

        // 1. Machine ID del SO
        if (file_exists('/etc/machine-id') && is_readable('/etc/machine-id')) {
            $components[] = trim((string) @file_get_contents('/etc/machine-id'));
        } else {
            $components[] = 'NO_MID';
        }

        // 2. Componentes DMI de BIOS y Placa Base
        $dmiProps = ['sys_vendor', 'product_name', 'board_name', 'bios_vendor', 'bios_version'];
        foreach ($dmiProps as $prop) {
            $p = "/sys/class/dmi/id/{$prop}";
            if (file_exists($p) && is_readable($p)) {
                $v = trim((string) @file_get_contents($p));
                if ($v !== '') {
                    $components[] = $v;
                }
            }
        }

        // 3. Hostname y Arquitectura
        $components[] = gethostname() ?: 'localhost';
        $components[] = php_uname('m') ?: 'x86_64';

        $raw = implode(':', $components);
        return hash_pbkdf2('sha256', $raw, self::SALT, self::ITERATIONS, self::KEY_LENGTH, true);
    }
}
