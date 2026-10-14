<?php

namespace App\Contracts;

use App\Models\InventoryAsset;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Contrato para el servicio de auditoría forense y trazabilidad cronológica de activos ITAM.
 */
interface AssetTimelineInterface
{
    /**
     * Retorna la línea de tiempo cronológica unificada de un activo específico.
     * Consolida: Asignaciones (UINF-4.05), Hardware Drifts, Mantenimientos y Pistas de Auditoría.
     */
    public function getAssetTimeline(InventoryAsset $asset): Collection;

    /**
     * Retorna el feed global paginado de auditoría y trazabilidad de activos ITAM.
     */
    public function getGlobalItamAuditFeed(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * Retorna métricas KPI de auditoría y ciclo de vida de activos ITAM.
     */
    public function getAuditKpis(): array;
}
