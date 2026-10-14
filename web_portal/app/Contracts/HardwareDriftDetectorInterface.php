<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

/**
 * Contrato para el motor de detección de discrepancias forenses de hardware (Hardware Drift).
 * Principio I (Interface Segregation) y D (Dependency Inversion).
 */
interface HardwareDriftDetectorInterface
{
    /**
     * Retorna el resumen consolidado de discrepancias de hardware.
     *
     * @return array{total_drift_count: int, ram_reductions: int, missing_disks: int, cpu_changes: int, other_changes: int}
     */
    public function getDriftSummary(): array;

    /**
     * Retorna la colección de activos con discrepancias activas no regularizadas.
     */
    public function getAssetsWithDrift(?int $siteId = null): Collection;

    /**
     * Audita un activo específico para determinar si presenta discrepancias activas.
     */
    public function checkAssetDrift(int $assetId): array;

    /**
     * Registra la regularización o justificación de una discrepancia de hardware por parte del Administrador.
     */
    public function acknowledgeDrift(int $assetId, string $reason, int $userId): bool;
}
