<?php

namespace App\Services\Glpi;

use App\Contracts\HardwareDriftDetectorInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryComponentHistory;
use Illuminate\Support\Collection;

/**
 * Servicio de Detección y Auditoría Forense de Discrepancias de Hardware (Hardware Drift).
 * Responsabilidad Única (S): Identificar variaciones de hardware, cuantificar discrepancias y registrar justificaciones.
 */
class HardwareDriftDetectorService implements HardwareDriftDetectorInterface
{
    /**
     * Retorna el resumen consolidado de discrepancias de hardware.
     */
    public function getDriftSummary(): array
    {
        $assetsWithDrift = $this->getAssetsWithDrift();

        $ramReductions = 0;
        $missingDisks = 0;
        $cpuChanges = 0;
        $otherChanges = 0;

        foreach ($assetsWithDrift as $item) {
            match ($item['primary_drift_type'] ?? '') {
                'ram' => $ramReductions++,
                'disk' => $missingDisks++,
                'cpu' => $cpuChanges++,
                default => $otherChanges++,
            };
        }

        return [
            'total_drift_count' => $assetsWithDrift->count(),
            'ram_reductions' => $ramReductions,
            'missing_disks' => $missingDisks,
            'cpu_changes' => $cpuChanges,
            'other_changes' => $otherChanges,
        ];
    }

    /**
     * Retorna la colección de activos con discrepancias activas no regularizadas.
     */
    public function getAssetsWithDrift(?int $siteId = null): Collection
    {
        $query = InventoryAsset::query()
            ->with(['site', 'activeAssignment', 'componentHistories'])
            ->whereHas('componentHistories');

        if ($siteId) {
            $query->where('monitored_site_id', $siteId);
        }

        $assets = $query->get();
        $driftList = collect();

        foreach ($assets as $asset) {
            $audit = $this->evaluateAssetHistories($asset);
            if ($audit['has_drift']) {
                $driftList->push([
                    'asset_id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'name' => $asset->name, // Nombre Etiqueta corporativo (SSOT Regla #7)
                    'ip_address' => $asset->ip_address,
                    'mac_address' => $asset->mac_address,
                    'monitored_site_id' => $asset->monitored_site_id,
                    'site_name' => $asset->site?->name ?? 'Sin sede asignada',
                    'responsable' => $asset->activeAssignment?->recipient_name
                        ?? $asset->custodian_name
                        ?? 'No asignado',
                    'primary_drift_type' => $audit['primary_type'],
                    'severity' => $audit['severity'],
                    'events_count' => count($audit['events']),
                    'events' => $audit['events'],
                    'detected_at' => $audit['latest_drift_at'],
                ]);
            }
        }

        return $driftList->sortByDesc('detected_at')->values();
    }

    /**
     * Audita un activo específico para determinar si presenta discrepancias activas.
     */
    public function checkAssetDrift(int $assetId): array
    {
        $asset = InventoryAsset::with(['site', 'activeAssignment', 'componentHistories'])->find($assetId);
        if (!$asset) {
            return [
                'has_drift' => false,
                'message' => 'Activo no encontrado',
                'events' => [],
            ];
        }

        return $this->evaluateAssetHistories($asset);
    }

    /**
     * Registra la regularización o justificación de una discrepancia de hardware por parte del Administrador.
     */
    public function acknowledgeDrift(int $assetId, string $reason, int $userId): bool
    {
        $asset = InventoryAsset::find($assetId);
        if (!$asset) {
            return false;
        }

        $now = now();
        $cleanReason = trim($reason) ?: 'Discrepancia regularizada formalmente por el Administrador';

        // 1. Insertar evento de regularización en el historial de componentes
        InventoryComponentHistory::create([
            'inventory_asset_id' => $asset->id,
            'action' => 'acknowledged',
            'component_type' => 'all',
            'old_value' => null,
            'new_value' => ['status' => 'acknowledged'],
            'reason' => $cleanReason,
            'registered_by_user_id' => $userId,
            'recorded_at' => $now,
        ]);

        // 2. Registrar en la bitácora central de auditoría
        AuditLog::create([
            'user_id' => $userId,
            'event' => 'hardware_drift_acknowledged',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->name,
            'entity_label' => $asset->asset_tag,
            'description' => "Regularización de discrepancia de hardware en {$asset->name}: {$cleanReason}",
            'new_values' => ['reason' => $cleanReason, 'acknowledged_at' => $now->toDateTimeString()],
        ]);

        return true;
    }

    /**
     * Evalúa las bitácoras inmutables de un activo para determinar discrepancias activas.
     */
    protected function evaluateAssetHistories(InventoryAsset $asset): array
    {
        $histories = $asset->relationLoaded('componentHistories')
            ? $asset->componentHistories->sortByDesc('recorded_at')
            : $asset->componentHistories()->orderBy('recorded_at', 'desc')->get();

        if ($histories->isEmpty()) {
            return [
                'has_drift' => false,
                'severity' => 'none',
                'primary_type' => null,
                'latest_drift_at' => null,
                'events' => [],
            ];
        }

        // Buscar la fecha de la última regularización (si existe)
        $latestAck = $histories->firstWhere('action', 'acknowledged');
        $ackBenchmark = $latestAck?->recorded_at;

        $driftEvents = [];
        $severity = 'warning';
        $primaryType = null;
        $latestDriftAt = null;

        foreach ($histories as $h) {
            // Ignorar eventos anteriores o iguales a la última regularización
            if ($ackBenchmark && $h->recorded_at <= $ackBenchmark) {
                continue;
            }

            // Identificar si el evento representa una reducción, retiro o cambio crítico
            $isRemoval = $h->action === 'removed';
            $isReduction = false;
            $reason = (string)($h->reason ?? '');

            if (str_contains($reason, 'Sustracción') || str_contains($reason, 'Disminución') || str_contains($reason, 'falla de módulo')) {
                $isReduction = true;
            }

            // Si es retiro o reducción, es un Hardware Drift de alta severidad
            if ($isRemoval || $isReduction) {
                $severity = 'critical';
                if (!$primaryType) {
                    $primaryType = $h->component_type;
                }
                if (!$latestDriftAt) {
                    $latestDriftAt = $h->recorded_at;
                }

                $driftEvents[] = [
                    'id' => $h->id,
                    'action' => $h->action,
                    'component_type' => $h->component_type,
                    'old_value' => $h->old_value,
                    'new_value' => $h->new_value,
                    'reason' => $h->reason,
                    'recorded_at' => $h->recorded_at?->format('d/m/Y H:i:s'),
                ];
            }
        }

        return [
            'has_drift' => !empty($driftEvents),
            'severity' => !empty($driftEvents) ? $severity : 'none',
            'primary_type' => $primaryType ?? ($driftEvents[0]['component_type'] ?? null),
            'latest_drift_at' => $latestDriftAt?->format('d/m/Y H:i:s'),
            'events' => $driftEvents,
        ];
    }
}
