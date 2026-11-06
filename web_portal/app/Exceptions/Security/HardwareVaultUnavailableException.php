<?php

declare(strict_types=1);

namespace App\Exceptions\Security;

use RuntimeException;

/**
 * Excepción de Dominio lanzada cuando la bóveda anclada al hardware es inaccesible,
 * carece de permisos de lectura en el sistema de archivos o su firma HMAC es inválida.
 * 
 * Previene fallbacks silenciosos o peligrosos a bots de licenciamiento o DRM.
 */
class HardwareVaultUnavailableException extends RuntimeException
{
    public static function unreadable(string $path, string $user): self
    {
        return new self("La bóveda física de hardware en '{$path}' no es legible por el usuario del sistema '{$user}'. Verifique permisos Unix (0750/0640).");
    }

    public static function invalidSignature(): self
    {
        return new self('La firma criptográfica HMAC de la bóveda no coincide con los componentes de hardware del servidor.');
    }

    public static function missingToken(): self
    {
        return new self('No se pudo resolver el token operacional desde la bóveda de hardware ni existe token en MariaDB.');
    }
}
