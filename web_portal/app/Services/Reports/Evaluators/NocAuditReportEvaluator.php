<?php

declare(strict_types=1);

namespace App\Services\Reports\Evaluators;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use Carbon\Carbon;

/**
 * Evaluador de datos para reportes de auditoría de cumplimiento, ciclo de vida y SLA NOC.
 */
class NocAuditReportEvaluator
{
    public function __construct(
        protected IpamReportEvaluator $ipamEvaluator
    ) {}

    /**
     * Retorna los datos normalizados de la Matriz IPAM delegando al evaluador especializado.
     */
    public function getIpamData(): array
    {
        return $this->ipamEvaluator->getIpamData();
    }

    /**
     * Retorna los datos de Auditoría de Cumplimiento de Actas UINF-4.05 y Pases de Movilización.
     */
    public function getComplianceData(): array
    {
        $assignments = InventoryAssignment::with(['asset', 'site', 'deliveredByUser'])
            ->orderBy('assigned_at', 'desc')
            ->get();

        $rows = [];
        foreach ($assignments as $a) {
            $assignedDate = $a->assigned_at ? Carbon::parse($a->assigned_at)->format('d/m/Y') : 'N/A';
            $hasOutdated = $a->asset?->has_outdated_assignment ? 'Sí (Hardware Alterado)' : 'No';

            $rows[] = [
                'assignment_code' => $a->assignment_code,
                'movement_type' => $a->movement_type_label,
                'asset_identity' => ($a->asset?->display_identity ?? $a->asset?->name ?? 'N/A') . " (" . ($a->asset?->asset_tag ?? 'N/A') . ")",
                'responsable' => $a->custodian_name ?: 'Funcionario Receptor',
                'id_number' => $a->custodian_id_number ?: 'N/A',
                'department' => $a->custodian_department ?: 'N/A',
                'site_name' => $a->site?->name ?? 'Sede Central',
                'assigned_date' => $assignedDate,
                'days_assigned' => "{$a->days_since_assigned} días",
                'scan_status' => $a->scan_compliance_label,
                'hardware_drift' => $hasOutdated,
            ];
        }

        return [
            'title' => 'Auditoría de Actas de Entrega UINF-4.05 & Movilizaciones',
            'headers' => [
                'Código de Acta',
                'Tipo de Trámite',
                'Activo Asignado',
                'Funcionario Responsable',
                'Cédula / Identificación',
                'Departamento / Unidad',
                'Sede Física',
                'Fecha Asignación',
                'Tiempo Asignado',
                'Estatus Soporte Digital',
                'Hardware Modificado',
            ],
            'rows' => $rows,
            'summary' => [
                'Total Actas Registradas' => count($rows),
                'Soporte Digital' => 'Requerido',
                'Fecha de Generación' => now()->format('d/m/Y H:i:s'),
            ],
        ];
    }

    /**
     * Retorna los datos de Ciclo de Vida, Hardware Drift y Garantías.
     */
    public function getLifecycleData(): array
    {
        $assets = InventoryAsset::with(['components', 'componentHistories', 'site'])
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($assets as $a) {
            $driftCount = $a->componentHistories->count();
            $warrantyDate = $a->warranty_expiration ? Carbon::parse($a->warranty_expiration)->format('d/m/Y') : 'Sin Registro';

            $warrantyStatus = 'Sin Registro';
            if ($a->warranty_expiration) {
                $days = Carbon::now()->diffInDays(Carbon::parse($a->warranty_expiration), false);
                if ($days < 0) {
                    $warrantyStatus = 'Vencida';
                } elseif ($days <= 30) {
                    $warrantyStatus = "Por Vencer ({$days}d)";
                } else {
                    $warrantyStatus = "Vigente ({$days}d)";
                }
            }

            $lastSync = $a->last_glpi_sync_at ? Carbon::parse($a->last_glpi_sync_at)->format('d/m/Y H:i') : 'No sincronizado';

            $rows[] = [
                'asset_identity' => $a->display_identity ?? $a->name,
                'asset_tag' => $a->asset_tag,
                'serial_number' => $a->serial_number ?: 'N/A',
                'site_name' => $a->site?->name ?? 'Sede Central',
                'warranty_expiration' => $warrantyDate,
                'warranty_status' => $warrantyStatus,
                'hardware_drifts' => $driftCount > 0 ? "{$driftCount} alteración(es)" : 'Ninguna',
                'last_sync' => $lastSync,
                'status_label' => $a->status_label,
            ];
        }

        return [
            'title' => 'Ciclo de Vida, Modificaciones de Hardware & Garantías',
            'headers' => [
                'Nombre Etiqueta',
                'Etiqueta Bien',
                'Número de Serie',
                'Sede Física',
                'Vencimiento Garantía',
                'Estatus de Garantía',
                'Drifts de Hardware',
                'Último Sondeo GLPI',
                'Estado Operativo',
            ],
            'rows' => $rows,
            'summary' => [
                'Total Equipos Inspeccionados' => count($rows),
                'Parque Tecnológico' => 'Gerencia de TI Valle Seco',
                'Fecha de Generación' => now()->format('d/m/Y H:i:s'),
            ],
        ];
    }

    /**
     * Retorna los datos de Disponibilidad SLA 24/7 de Enlaces WAN y Servicios NOC.
     */
    public function getSlaData(): array
    {
        $sites = MonitoredSite::all();
        $services = MonitoredService::all();
        $proxies = MonitoredProxy::all();

        $rows = [];
        foreach ($sites as $s) {
            $status = $s->is_active ? 'Operativo (UP)' : 'Inactivo / Pausado';
            $rows[] = [
                'entity_type' => 'Sede / Enlace WAN',
                'entity_name' => $s->name,
                'target_ip' => $s->ip,
                'status' => $status,
                'metric' => "Monitoreo: " . ($s->is_active ? 'Activo' : 'Inactivo'),
                'notes' => $s->address ?: "Sede física registrada",
            ];
        }

        foreach ($services as $srv) {
            $status = $srv->is_active ? 'Operativo (UP)' : 'Inactivo / Pausado';
            $rows[] = [
                'entity_type' => 'Servicio Monitoreado',
                'entity_name' => $srv->name,
                'target_ip' => $srv->host_ip ?: ($srv->web_url ?: 'Local'),
                'status' => $status,
                'metric' => "Puerto: " . ($srv->port ?: 'Web'),
                'notes' => $srv->type ?? 'Servicio de red',
            ];
        }

        foreach ($proxies as $p) {
            $status = $p->is_active ? 'Operativo (UP)' : 'Inactivo';
            $rows[] = [
                'entity_type' => 'Servidor Proxy',
                'entity_name' => $p->name,
                'target_ip' => $p->ip_port ?? 'N/A',
                'status' => $status,
                'metric' => "Proxy HTTP / SOCKS",
                'notes' => 'Pasarela de acceso proxy',
            ];
        }

        return [
            'title' => 'Disponibilidad de Red & Servicios NOC • Supervisión 24/7',
            'headers' => [
                'Tipo de Entidad',
                'Nombre / Descripción',
                'Destino IP / Puerto',
                'Estado Operativo',
                'Métrica de Conectividad',
                'Observaciones',
            ],
            'rows' => $rows,
            'summary' => [
                'Total Entidades Monitoreadas' => count($rows),
                'Centro de Control' => 'NOC Valle Seco',
                'Fecha de Generación' => now()->format('d/m/Y H:i:s'),
            ],
        ];
    }
}
