<?php

declare(strict_types=1);

namespace App\Services\Reports\Evaluators;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponentHistory;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;

/**
 * Evaluador de catálogo descriptivo y métricas cuantitativas para el centro de reportes.
 */
class ReportCatalogEvaluator
{
    /**
     * Retorna el catálogo descriptivo de reportes disponibles.
     */
    public function getAvailableReports(): array
    {
        return [
            'census' => [
                'id' => 'census',
                'title' => 'Censo Maestro NOC + ITAM',
                'subtitle' => 'Inventario Unificado de Infraestructura y Activos de TI',
                'description' => 'Relación consolidada de todos los servidores, conmutadores, enrutadores, estaciones de trabajo y periféricos con Nombre Etiqueta, IP, MAC, sede física y funcionario responsable.',
                'icon' => 'devices',
                'badge' => 'Censo Maestro',
                'badge_class' => 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30',
            ],
            'compliance' => [
                'id' => 'compliance',
                'title' => 'Cumplimiento de Actas UINF-4.05',
                'subtitle' => 'Asignaciones de Equipos, Movilizaciones',
                'description' => 'Relación de actas de entrega/recepción, estatus de digitalización y pases de movilización activos.',
                'icon' => 'assignment_turned_in',
                'badge' => 'Control Legal & SLA',
                'badge_class' => 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30',
            ],
            'lifecycle' => [
                'id' => 'lifecycle',
                'title' => 'Ciclo de Vida, Hardware Drift & Garantías',
                'subtitle' => 'Salud del Parque Tecnológico & Mantenimientos',
                'description' => 'Trazabilidad de modificaciones de hardware post-entrega (aumento/retiro de memoria RAM o discos), vigencia de garantías del fabricante y órdenes de servicio técnico.',
                'icon' => 'memory',
                'badge' => 'Hardware & Soporte',
                'badge_class' => 'bg-amber-500/15 text-amber-400 border border-amber-500/30',
            ],
            'sla' => [
                'id' => 'sla',
                'title' => 'Disponibilidad de Red & Servicios NOC',
                'subtitle' => 'Supervisión 24/7 de Sedes Remotas, WAN y Proxies',
                'description' => 'Métricas de disponibilidad operativa de las sedes físicas, enlaces WAN troncales, proxies corporativos y servicios de telecomunicaciones.',
                'icon' => 'hub',
                'badge' => 'Operatividad NOC',
                'badge_class' => 'bg-blue-500/15 text-blue-400 border border-blue-500/30',
            ],
        ];
    }

    /**
     * Retorna métricas cuantitativas y KPIs para el centro de reportes.
     */
    public function getReportMetrics(): array
    {
        $totalAssets = InventoryAsset::count();
        $totalEntities = $totalAssets; // MariaDB SSOT

        $totalAssignments = InventoryAssignment::count();
        $activeAssignments = InventoryAssignment::where('status', 'active')->count();
        $overdueAssignments = InventoryAsset::where('status', 'assigned')->get()
            ->filter(fn ($a) => $a->has_outdated_assignment || $a->is_scan_overdue)
            ->count();

        $complianceRate = $activeAssignments > 0
            ? round((($activeAssignments - $overdueAssignments) / $activeAssignments) * 100, 1)
            : 100.0;

        $totalSites = MonitoredSite::count();
        $upSites = MonitoredSite::where('is_active', true)->count();
        $slaRate = $totalSites > 0 ? round(($upSites / $totalSites) * 100, 1) : 100.0;

        return [
            'total_entities' => $totalEntities,
            'total_assignments' => $totalAssignments,
            'active_assignments' => $activeAssignments,
            'compliance_rate' => max(0, min(100, $complianceRate)),
            'sla_rate' => max(0, min(100, $slaRate)),
            'hardware_drifts' => InventoryComponentHistory::count(),
        ];
    }
}
