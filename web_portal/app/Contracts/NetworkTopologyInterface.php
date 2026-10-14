<?php

namespace App\Contracts;

/**
 * Contrato para la generación y gestión de la topología dinámica de red.
 * Principio D (Dependency Inversion) e I (Interface Segregation).
 */
interface NetworkTopologyInterface
{
    /**
     * Construye y retorna la estructura de nodos y enlaces para Cytoscape.js.
     *
     * @param int|null $siteId ID de sede opcional para filtrar la cartografía.
     * @return array Grafo con 'elements' => ['nodes', 'edges'] y 'meta' => [...]
     */
    public function getTopologyGraph(?int $siteId = null): array;

    /**
     * Dispara la re-evaluación y reconstrucción de enlaces en MariaDB.
     *
     * @return array Resultado de la reconstrucción (success, message, linkCount)
     */
    public function rebuildTopology(): array;
}
