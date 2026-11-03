<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Contracts\Services\BotTokenResolverInterface;
use App\Contracts\Services\HardwareVaultReaderInterface;
use App\Exceptions\Security\HardwareVaultUnavailableException;
use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

/**
 * Resolutor oficial del Token Operacional de Telegram (@ValleSeco_bot) para la plataforma web.
 * 
 * Principio SOLID (SRP + DIP):
 * - Responsabilidad Única: Resolver exclusivamente las credenciales del bot operacional.
 * - Inversión de Dependencias: Consume HardwareVaultReaderInterface inyectada.
 * - Regla Inviolable: CERO referencias o fallbacks hacia bots de licencias o DRM.
 */
class OperationalBotTokenResolver implements BotTokenResolverInterface
{
    private ?string $cachedToken = null;
    private const FALLBACK_OWNER_ID = 38914901;

    public function __construct(
        private readonly HardwareVaultReaderInterface $vaultReader
    ) {
    }

    /**
     * Resuelve el token oficial de @ValleSeco_bot desde la bóveda física en RAM.
     * 
     * @throws HardwareVaultUnavailableException Si la bóveda no está legible y no hay token válido en MariaDB.
     */
    public function resolve(): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        // 1. Intentar desencriptar directamente desde la Bóveda de Hardware
        $vaultToken = $this->vaultReader->unwrap();
        if ($vaultToken !== null && str_contains($vaultToken, ':') && strlen($vaultToken) >= 30) {
            $this->cachedToken = $vaultToken;
            return $this->cachedToken;
        }

        // 2. Si MariaDB contiene un token no-encriptado válido (migración o entorno específico)
        try {
            $dbToken = (string) (BotSetting::get('bot_token') ?: '');
            if ($dbToken !== '' && $dbToken !== '[ENCRYPTED_HARDWARE_VAULT]' && str_contains($dbToken, ':') && strlen($dbToken) >= 30) {
                $this->cachedToken = $dbToken;
                return $this->cachedToken;
            }
        } catch (\Throwable $e) {
            Log::warning('OperationalBotTokenResolver: Error consultando bot_settings en MariaDB: ' . $e->getMessage());
        }

        // 3. Si no se puede resolver, lanzar excepción formal de dominio (CERO fallbacks silenciosos a DRM)
        throw HardwareVaultUnavailableException::missingToken();
    }

    /**
     * Obtiene el ID numérico del Owner/Administrador oficial.
     */
    public function getOwnerId(): int
    {
        try {
            $dbOwner = BotSetting::get('owner_id');
            if (!empty($dbOwner) && is_numeric($dbOwner)) {
                return (int) $dbOwner;
            }
        } catch (\Throwable) {
            // Ignorar y caer al ID inmutable oficial
        }

        return self::FALLBACK_OWNER_ID;
    }

    /**
     * Limpia la caché en RAM para pruebas o refresco en caliente.
     */
    public function clearCache(): void
    {
        $this->cachedToken = null;
    }
}
