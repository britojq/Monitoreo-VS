<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Contracts\Services\BotTokenResolverInterface;
use App\Contracts\Services\HardwareVaultReaderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Servicio Criptográfico de Bóveda Anclada al Hardware (Hardware-Bound Vault).
 * Fachada de compatibilidad hacia la arquitectura desacoplada SOLID.
 * 
 * En cumplimiento de la Regla de Oro #13:
 * - Los tokens definitivos residen EXCLUSIVAMENTE cifrados en la bóveda de hardware local.
 * - En disco y MariaDB el valor visible es exclusivamente [ENCRYPTED_HARDWARE_VAULT].
 * - El token desencriptado existe únicamente en la memoria RAM volátil del proceso en ejecución.
 * - CERO referencias o despachos hacia bots de licenciamiento o DRM (Regla de Oro #1 y directiva de Owner).
 */
class HardwareVaultService
{
    /**
     * Resuelve el Token del Bot Operacional (@ValleSeco_bot) de forma segura desde la bóveda de hardware.
     */
    public static function resolveBotToken(): string
    {
        try {
            if (app()->bound(BotTokenResolverInterface::class)) {
                return app(BotTokenResolverInterface::class)->resolve();
            }
        } catch (\Throwable $e) {
            Log::warning('HardwareVaultService: Error resolviendo token desde BotTokenResolverInterface: ' . $e->getMessage());
        }

        // Fallback directo a HardwareVaultReaderService sin tocar Sentinel
        $reader = new HardwareVaultReaderService();
        $unwrapped = $reader->unwrap();
        if ($unwrapped !== null) {
            return $unwrapped;
        }

        return '';
    }

    /**
     * Desencripta el token de bot desde system/vault/.sys_anchor utilizando PBKDF2 derivado de los componentes físicos.
     */
    public static function unwrapFromHardwareAnchor(): ?string
    {
        $reader = new HardwareVaultReaderService();
        return $reader->unwrap();
    }

    /**
     * @deprecated El token centinela de DRM queda estrictamente aislado de la plataforma web.
     */
    public static function getSentinelToken(): string
    {
        Log::warning('HardwareVaultService::getSentinelToken invocado de forma no autorizada. Retornando cadena vacía.');
        return '';
    }
}
