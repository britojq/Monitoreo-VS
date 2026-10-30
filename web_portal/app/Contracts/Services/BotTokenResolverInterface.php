<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Contrato formal para la resolución del Token Operacional del Bot de Telegram en Laravel.
 * Principio DIP: Todo transporte o servicio que requiera comunicarse con Telegram
 * depende exclusivamente de esta interfaz y no de clases estáticas ni implementaciones concretas.
 */
interface BotTokenResolverInterface
{
    /**
     * Resuelve el token oficial del bot operacional (@ValleSeco_bot).
     * Lanza HardwareVaultUnavailableException si el token no puede resolverse.
     * CERO fallbacks a tokens centinela o de DRM.
     */
    public function resolve(): string;

    /**
     * Resuelve el ID inmutable o configurado del Administrador (Owner).
     */
    public function getOwnerId(): int;
}
