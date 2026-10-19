<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato formal para el Motor del Grafo de Conocimiento del Sistema (Graph RAG).
 * Cumple con SOLID: Interface Segregation (I) y Dependency Inversion (D).
 */
interface SystemKnowledgeGraphInterface
{
    /**
     * Construye o refresca el grafo de infraestructura desde MariaDB (SSOT).
     *
     * @param bool $forceRefresh Forzar reconstrucción ignorando caché
     * @return array Resumen cuantitativo del grafo (nodos, aristas, entidades)
     */
    public function buildGraph(bool $forceRefresh = false): array;

    /**
     * Resuelve el contexto relacional libre de ambigüedades para la consulta.
     *
     * @param string $query Mensaje o pregunta del usuario
     * @param string|null $activeNodeId Identificador del nodo previamente activo en la sesión
     * @return array Tupla ['context' => string, 'active_node_id' => string|null]
     */
    public function resolveQueryContext(string $query, ?string $activeNodeId = null): array;

    /**
     * Retorna el histórico y telemetría de eventos de energía del UPS.
     *
     * @param int|null $upsId ID del UPS (opcional, por defecto el principal)
     * @return array Métricas, conteo total y eventos recientes de energía
     */
    public function getUpsPowerEventsSummary(?int $upsId = null): array;
}
