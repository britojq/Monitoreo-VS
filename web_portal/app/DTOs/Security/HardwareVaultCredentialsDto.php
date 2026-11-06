<?php

declare(strict_types=1);

namespace App\DTOs\Security;

/**
 * Data Transfer Object inmutable para las credenciales operacionales resueltas desde la Bóveda.
 * Principio SOLID: Inmutabilidad garantizada mediante readonly class de PHP 8.4.
 */
final readonly class HardwareVaultCredentialsDto
{
    public function __construct(
        public string $botToken,
        public int $ownerId,
        public string $source = 'hardware_vault'
    ) {
    }

    /**
     * Valida si el token cumple con la estructura requerida por Telegram Bot API (ID:HASH).
     */
    public function isValid(): bool
    {
        return str_contains($this->botToken, ':') && strlen($this->botToken) >= 30;
    }
}
