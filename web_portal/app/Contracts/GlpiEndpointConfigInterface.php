<?php

declare(strict_types=1);

namespace App\Contracts;

interface GlpiEndpointConfigInterface
{
    /**
     * Retorna la IP o hostname vivo del servidor actual.
     */
    public function getCurrentHost(): string;

    /**
     * Retorna la URL receptora completa correspondiente al servidor actual.
     */
    public function getCurrentHostUrl(): string;

    /**
     * Retorna la configuración completa de endpoints GLPI persistida en MariaDB.
     *
     * @return array{
     *     current_host: string,
     *     current_url: string,
     *     current_deploy_base: string,
     *     prod_url: string,
     *     prod_host: string,
     *     prod_deploy_base: string,
     *     dev_url: string,
     *     dev_host: string,
     *     dev_deploy_base: string,
     *     token: string
     * }
     */
    public function getEndpointsConfig(): array;

    /**
     * Resuelve la URL de destino según la opción elegida (auto/current, prod, dev, custom).
     *
     * @return array{
     *     server_url: string,
     *     deploy_base_url: string,
     *     target_label: string,
     *     target_host: string
     * }
     */
    public function resolveTargetUrl(string $target, ?string $customIp = null): array;

    /**
     * Actualiza la configuración de endpoints en MariaDB.
     *
     * @param array<string, mixed> $data
     */
    public function updateEndpoints(array $data): bool;
}
