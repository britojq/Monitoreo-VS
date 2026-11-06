<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Contrato formal para la lectura y desencriptado de la bóveda anclada al hardware (system/vault/.sys_anchor).
 * Principio DIP: Desacopla la infraestructura criptográfica de los consumidores de tokens.
 */
interface HardwareVaultReaderInterface
{
    /**
     * Intenta desencriptar y extraer el token operacional desde la bóveda física en RAM volátil.
     * Retorna null si el archivo no existe, no tiene permisos o la firma HMAC es inválida.
     */
    public function unwrap(): ?string;

    /**
     * Verifica si la bóveda física se encuentra disponible y legible en el sistema.
     */
    public function isAvailable(): bool;
}
