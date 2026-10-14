<?php

namespace App\Contracts;

/**
 * Contrato para el motor de Gestión Visual de Direcciones IP (IPAM Grid).
 * Principio I (Interface Segregation) y D (Dependency Inversion).
 */
interface IpamGridInterface
{
    /**
     * Retorna la matriz completa de 256 celdas (red, broadcast y 254 hosts) para un segmento CIDR.
     *
     * @return array{
     *     cidr: string,
     *     base_ip: string,
     *     metrics: array{
     *         total_hosts: int,
     *         total_used: int,
     *         official_count: int,
     *         official_up_count: int,
     *         official_down_count: int,
     *         rogue_count: int,
     *         free_count: int,
     *         utilization_pct: float
     *     },
     *     cells: array<int, array>
     * }
     */
    public function getSubnetGrid(string $cidr): array;

    /**
     * Retorna la lista de subredes corporativas conocidas derivadas de sedes oficiales en MariaDB.
     *
     * @return array<int, array{id: int|string, name: string, cidr: string, gateway_ip: string}>
     */
    public function getAvailableSubnets(): array;
}
