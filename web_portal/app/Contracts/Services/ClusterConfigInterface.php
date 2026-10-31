<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Contrato de Inversión de Dependencias (DIP) para el servicio de configuración de clúster.
 * Permite desacoplar los controladores administrativos de la implementación concreta.
 */
interface ClusterConfigInterface
{
    /**
     * Obtiene la configuración del nodo de clúster desde la fuente de verdad (MariaDB SSOT).
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * Retorna el rol del nodo ('master', 'slave', 'dev').
     */
    public function getNodeRole(): string;

    /**
     * Determina si el nodo actual opera como Maestro.
     */
    public function isMaster(): bool;

    /**
     * Determina si el nodo actual opera como Esclavo (Nodo Remoto).
     */
    public function isSlave(): bool;

    /**
     * Determina si el nodo actual opera en modo Desarrollo.
     */
    public function isDev(): bool;

    /**
     * Retorna el token criptográfico de autenticación inter-nodo.
     */
    public function getClusterToken(): string;

    /**
     * Retorna la URL de la API del nodo maestro.
     */
    public function getMasterApiUrl(): string;

    /**
     * Actualiza y persiste los atributos de configuración del clúster en SSOT.
     *
     * @param array<string, mixed> $attributes
     */
    public function updateConfig(array $attributes): bool;
}
