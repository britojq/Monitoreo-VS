<?php

namespace App\Services\Reports;

use App\Contracts\ExecutiveReportInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponentHistory;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\NetRadarHost;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Servicio unificado para la generación de reportes consolidados NOC + ITAM (MariaDB SSOT).
 */
class ExecutiveReportService implements ExecutiveReportInterface
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
        $totalNetDevices = MonitoredNetworkDevice::count();
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

    /**
     * Retorna los datos normalizados para el Censo Maestro NOC + ITAM.
     */
    public function getCensusData(): array
    {
        $assets = InventoryAsset::with(['site', 'activeAssignment', 'peripherals'])
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($assets as $a) {
            $responsable = $a->activeAssignment?->recipient_name 
                ?? $a->custodian_name 
                ?? 'No asignado';

            $depto = $a->activeAssignment?->custodian_department ?? 'N/A';
            $peripheralsCount = $a->peripherals->count();

            $rows[] = [
                'display_identity' => $a->display_identity ?? $a->name, // Nombre Etiqueta (SSOT Regla #7)
                'technical_subtitle' => $a->technical_subtitle ?? 'N/A', // Hostname BIOS
                'type_label' => $a->type_label,
                'site_name' => $a->site?->name ?? 'Sede Central',
                'ip_address' => $a->ip_address ?: 'Sin IP fija',
                'mac_address' => $a->mac_address ?: 'N/A',
                'brand' => $a->brand ?: 'Genérico',
                'model' => $a->model ?: 'N/A',
                'serial_number' => $a->serial_number ?: 'N/A',
                'asset_tag' => $a->asset_tag ?: 'N/A',
                'status_label' => $a->status_label,
                'responsable' => $responsable,
                'department' => $depto,
                'peripherals_info' => $peripheralsCount > 0 ? "{$peripheralsCount} periférico(s)" : 'Ninguno',
            ];
        }

        return [
            'title' => 'Censo Maestro NOC + ITAM • Inventario Unificado',
            'headers' => [
                'Nombre Etiqueta',
                'Nombre de Equipo',
                'Tipo de Activo',
                'Sede Física',
                'Dirección IP',
                'Dirección MAC',
                'Fabricante',
                'Modelo',
                'Número de Serie',
                'Etiqueta Bien',
                'Estado Operativo',
                'Funcionario Responsable',
                'Departamento',
                'Periféricos',
            ],
            'rows' => $rows,
            'summary' => [
                'Total Activos Registrados' => count($rows),
                'Sede Principal' => 'Sede Valle Seco',
                'Fecha de Generación' => now()->format('d/m/Y H:i:s'),
            ],
        ];
    }

    /**
     * Retorna los datos normalizados de la Matriz IPAM (256 Bloques).
     */
    public function getIpamData(): array
    {
        $assetsByIp = InventoryAsset::whereNotNull('ip_address')->get()->keyBy('ip_address');
        $netDevicesByIp = MonitoredNetworkDevice::all()->keyBy('ip');
        $hostsByIp = NetRadarHost::all()->keyBy('ip');

        $rows = [];
        for ($i = 1; $i <= 254; $i++) {
            $ip = "10.20.23.{$i}";
            $asset = $assetsByIp->get($ip);
            $netDev = $netDevicesByIp->get($ip);
            $host = $hostsByIp->get($ip);

            $isOnline = false;
            $mac = 'N/A';
            $vendor = 'N/A';

            if ($host) {
                $isOnline = $host->last_seen_at ? Carbon::parse($host->last_seen_at)->gt(now()->subHours(24)) : true;
                $mac = $host->mac ?: $mac;
                $vendor = $host->vendor ?: $vendor;
            } elseif ($netDev) {
                $isOnline = (bool)$netDev->is_active;
                $mac = $netDev->mac ?: $mac;
                $vendor = $netDev->vendor_data ?: $vendor;
            } elseif ($asset) {
                $isOnline = $asset->status !== 'down';
                $mac = $asset->mac_address ?: $mac;
                $vendor = $asset->brand ?: $vendor;
            }

            $entityName = $asset?->name ?? ($netDev?->name ?? ($host?->hostname ?? null));
            $statusAssignment = 'Libre';
            if ($asset || $netDev) {
                $statusAssignment = 'Asignada (ITAM/NOC)';
            } elseif ($isOnline) {
                $statusAssignment = 'Host Activo (Sin ITAM)';
            }

            $authStatus = ($asset || $netDev) ? 'Autorizado' : ($isOnline ? 'Alerta Rogue / No Censado' : 'Disponible');

            // Solo incluir en reporte hosts asignados o activos para mantener tamaño conciso
            if ($asset || $netDev || $isOnline) {
                $rows[] = [
                    'ip_address' => $ip,
                    'ping_status' => $isOnline ? 'Activo (En Línea)' : 'Inactivo',
                    'assignment_type' => $statusAssignment,
                    'entity_name' => $entityName ?: 'Desconocido',
                    'mac_address' => $mac,
                    'vendor' => $vendor,
                    'auth_status' => $authStatus,
                    'last_seen' => $isOnline ? 'En línea' : 'Desconectado',
                ];
            }
        }

        return [
            'title' => 'Catálogo de Direccionamiento IPAM • Subred 10.20.23.0/24',
            'headers' => [
                'Dirección IP',
                'Estado Ping/ARP',
                'Tipo de Asignación',
                'Entidad / Nombre Etiqueta',
                'Dirección MAC',
                'Fabricante / OUI',
                'Estatus de Autorización',
                'Última Detección',
            ],
            'rows' => $rows,
            'summary' => [
                'Segmento de Red' => '10.20.23.0/24 (Valle Seco)',
                'Total Hosts Censados' => count($rows),
                'Fecha de Generación' => now()->format('d/m/Y H:i:s'),
            ],
        ];
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
                if ($days < 0) $warrantyStatus = 'Vencida';
                elseif ($days <= 30) $warrantyStatus = "Por Vencer ({$days}d)";
                else $warrantyStatus = "Vigente ({$days}d)";
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

    /**
     * Exporta el reporte solicitado en formato CSV (Excel UTF-8 estructurado).
     */
    public function exportCsv(string $type, string $category = 'all'): StreamedResponse
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');

        if ($type === 'census' && $category !== 'all') {
            return $this->exportCategoryCsv($category, $timestamp);
        }

        $data = $this->getReportData($type);
        $filename = "reporte_{$type}_{$timestamp}.csv";

        // Auditoría forense
        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Reporte [{$data['title']}] en formato CSV",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: strtoupper($type)
        );

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezado corporativo
            fputcsv($handle, ['REPÚBLICA BOLIVARIANA DE VENEZUELA - MPPEE - empresa']);
            fputcsv($handle, [$data['title']]);
            fputcsv($handle, ['Fecha de Emisión: ' . now()->format('d/m/Y H:i:s')]);
            fputcsv($handle, []);

            // Resumen de indicadores
            if (!empty($data['summary'])) {
                fputcsv($handle, ['--- RESUMEN DE INDICADORES ---']);
                foreach ($data['summary'] as $k => $v) {
                    fputcsv($handle, [$k, $v]);
                }
                fputcsv($handle, []);
            }

            // Cabecera de datos
            fputcsv($handle, $data['headers']);

            // Filas
            foreach ($data['rows'] as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Exporta en CSV una categoría específica del inventario (servidores, telecom o computación).
     */
    protected function exportCategoryCsv(string $category, string $timestamp): StreamedResponse
    {
        $headers = [];
        $rows = [];
        $title = '';
        $filename = '';

        if ($category === 'servers') {
            $title = 'Inventario de Servidores • Infraestructura y Datacenter';
            $filename = "inventario_servidores_{$timestamp}.csv";
            $headers = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
            $rows = $this->getServerRows();
        } elseif ($category === 'telecom') {
            $title = 'Inventario de Telecomunicaciones • Switches, Routers, Telefonía IP y Red';
            $filename = "inventario_telecomunicaciones_{$timestamp}.csv";
            $headers = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
            $rows = $this->getTelecomRows();
        } else { // workstations
            $title = 'Inventario de Equipos de Computación & Periféricos de Trabajo';
            $filename = "inventario_equipos_computacion_{$timestamp}.csv";
            $headers = ['TIPO DE EQUIPO', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'SISTEMA OPERATIVO', 'APLICATIVOS INSTALADOS', 'NOMBRE Y APELLIDO', 'CÉDULA', 'TELÉFONO', 'ESTADO', 'UNIDAD', 'CONDICIONES', 'OBSERVACION'];
            $rows = $this->getWorkstationRows();
        }

        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Inventario Categorizado [{$category}] en formato CSV",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: strtoupper($category)
        );

        return response()->streamDownload(function () use ($title, $headers, $rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['REPÚBLICA BOLIVARIANA DE VENEZUELA - MPPEE - empresa']);
            fputcsv($handle, [$title]);
            fputcsv($handle, ['Gerencia General de ATIT • Sede Valle Seco']);
            fputcsv($handle, ['Fecha de Emisión: ' . now()->format('d/m/Y H:i:s')]);
            fputcsv($handle, []);
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Genera el reporte solicitado en formato PDF imprimible.
     */
    public function exportPdf(string $type): Response
    {
        $data = $this->getReportData($type);
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "reporte_{$type}_{$timestamp}.pdf";

        // Logotipos corporativos
        $empresaPath = public_path('assets/img/inventory/empresa_logo.png');
        $mppeePath = public_path('assets/img/inventory/mppee_logo.png');

        $empresaLogoBase64 = file_exists($empresaPath) ? base64_encode(file_get_contents($empresaPath)) : null;
        $mppeeLogoBase64 = file_exists($mppeePath) ? base64_encode(file_get_contents($mppeePath)) : null;

        // Auditoría forense
        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Reporte [{$data['title']}] en formato PDF",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: strtoupper($type)
        );

        $pdf = Pdf::loadView('admin.reports.pdf.executive_report', [
            'report' => $data,
            'type' => $type,
            'empresaLogoBase64' => $empresaLogoBase64,
            'mppeeLogoBase64' => $mppeeLogoBase64,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
            'auditHash' => strtoupper(hash('sha256', $type . now()->timestamp . 'VALLE_SECO_REPORTS')),
        ])->setPaper('letter', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Resuelve los datos del reporte según el tipo especificado.
     */
    protected function getReportData(string $type): array
    {
        return match ($type) {
            'census' => $this->getCensusData(),
            'ipam' => $this->getIpamData(),
            'compliance' => $this->getComplianceData(),
            'lifecycle' => $this->getLifecycleData(),
            'sla' => $this->getSlaData(),
            default => $this->getCensusData(),
        };
    }

    /**
     * Genera el libro Excel estructurado (SpreadsheetML) según tipo y categoría.
     */
    public function exportExcel(string $type = 'census', string $category = 'all'): Response
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');

        if ($type === 'census') {
            if ($category === 'servers') {
                $xml = $this->buildSingleCategorySpreadsheetXml(
                    sheetName: 'Servidores',
                    title: 'Inventario de Servidores • Infraestructura y Centro de Datos',
                    categoryDesc: 'SERVIDORES Y CENTRO DE DATOS',
                    rows: $this->getServerRows()
                );
                $filename = "Inventario_Servidores_{$timestamp}.xls";
                $logLabel = 'EXCEL_SERVIDORES';
            } elseif ($category === 'telecom') {
                $xml = $this->buildSingleCategorySpreadsheetXml(
                    sheetName: 'Telecomunicaciones',
                    title: 'Inventario de Telecomunicaciones • Switches, Routers, Telefonía IP y Red',
                    categoryDesc: 'TELECOMUNICACIONES Y DISPOSITIVOS DE RED',
                    rows: $this->getTelecomRows()
                );
                $filename = "Inventario_Telecomunicaciones_{$timestamp}.xls";
                $logLabel = 'EXCEL_TELECOMUNICACIONES';
            } elseif ($category === 'workstations') {
                $xml = $this->buildWorkstationCategorySpreadsheetXml($this->getWorkstationRows());
                $filename = "Inventario_Equipos_Computacion_{$timestamp}.xls";
                $logLabel = 'EXCEL_COMPUTACION';
            } else {
                $xml = $this->buildEquipmentInventorySpreadsheetXml();
                $filename = "Inventario_Equipos_Tecnologicos_{$timestamp}.xls";
                $logLabel = 'EXCEL_2_HOJAS';
            }
        } else {
            $xml = $this->buildGenericReportSpreadsheetXml($type);
            $filename = "Reporte_{$type}_{$timestamp}.xls";
            $logLabel = strtoupper($type);
        }

        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Reporte [{$type}] categoría [{$category}] en formato Excel",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: $logLabel
        );

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Retorna los registros normalizados de Servidores (formato Centro de Datos de 17 columnas).
     */
    public function getServerRows(): array
    {
        $serverAssets = InventoryAsset::with(['site', 'components'])
            ->where(function ($q) {
                $q->where('type', 'server')
                    ->orWhere('name', 'like', '%Servidor%')
                    ->orWhere('name', 'like', '%MONITOREO ATIT%')
                    ->orWhere('name', 'like', '%CENCARATIT%');
            })
            ->whereNotIn('type', ['switch', 'router'])
            ->get();

        $rows = [];
        $seenIps = [];

        foreach ($serverAssets as $a) {
            $ram = $a->components->where('type', 'ram')->first()?->capacity ?: '16 GB';
            $disk = $a->components->where('type', 'disk')->first()?->capacity ?: '1 TB';
            $cpu = $a->components->where('type', 'cpu')->first()?->name ?: 'INTEL CORE I7';

            $rows[] = [
                'rack' => $a->location_detail ?: 'RACK 01',
                'brand' => $a->brand ?: 'HP',
                'model' => $a->model ?: 'N/A',
                'serial' => $a->serial_number ?: 'N/A',
                'year' => $a->purchase_date ? $a->purchase_date->format('Y') : '2018',
                'ram' => $ram,
                'disk' => $disk,
                'os' => $a->operating_system ?: 'LINUX / DEBIAN',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => 'SERVIDOR',
                'cpu' => $cpu,
                'services' => $a->notes ?: 'MONITOREO / SERVICIOS NOC',
                'ip' => $a->ip_address ?: 'N/A',
                'mac' => $a->mac_address ?: 'N/A',
                'status' => $a->status_label,
                'admin' => $a->custodian_name ?: 'ADMINISTRADOR DE RED',
            ];
            if ($a->ip_address) {
                $seenIps[$a->ip_address] = true;
            }
        }

        $netServers = MonitoredNetworkDevice::where(function ($q) {
            $q->where('name', 'like', '%Servidor%')
                ->orWhere('name', 'like', '%MONITOREO%');
        })->get();

        foreach ($netServers as $ns) {
            if ($ns->ip && isset($seenIps[$ns->ip])) {
                continue;
            }
            $rows[] = [
                'rack' => 'RACK 01',
                'brand' => $ns->vendor_data ?: 'HEWLETT-PACKARD',
                'model' => $ns->model ?: 'SERVIDOR LOCAL',
                'serial' => $ns->serial ?: 'N/A',
                'year' => '2018',
                'ram' => '16 GB',
                'disk' => '1 TB',
                'os' => 'LINUX',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => 'SERVIDOR',
                'cpu' => 'INTEL CORE I7',
                'services' => $ns->notes ?: 'MONITOREO CONTINUO NOC',
                'ip' => $ns->ip ?: 'N/A',
                'mac' => $ns->mac ?: 'N/A',
                'status' => $ns->is_active ? 'OPERATIVO' : 'DAÑADO',
                'admin' => 'ADMINISTRADOR DE RED',
            ];
            if ($ns->ip) {
                $seenIps[$ns->ip] = true;
            }
        }

        return $rows;
    }

    /**
     * Retorna los registros normalizados de Telecomunicaciones (Switches, Routers, Telefonía IP, Firewalls y Red).
     */
    public function getTelecomRows(): array
    {
        $telecomAssets = InventoryAsset::with(['site', 'components'])
            ->where(function ($q) {
                $q->whereIn('type', ['switch', 'router', 'ip_phone', 'firewall', 'printer'])
                    ->orWhere('name', 'like', '%SW%')
                    ->orWhere('name', 'like', '%ROUTER%')
                    ->orWhere('name', 'like', '%PFsense%')
                    ->orWhere('name', 'like', '%AVAYA%');
            })
            ->where('name', 'not like', '%Servidor%')
            ->where('name', 'not like', '%CENCARATIT%')
            ->get();

        $rows = [];
        $seenIps = [];

        foreach ($telecomAssets as $a) {
            $ram = $a->components->where('type', 'ram')->first()?->capacity ?: 'N/A';
            $disk = $a->components->where('type', 'disk')->first()?->capacity ?: 'N/A';
            $cpu = $a->components->where('type', 'cpu')->first()?->name ?: 'N/A';

            $hardware = match ($a->type) {
                'switch' => 'CONMUTADOR (SWITCH)',
                'router' => 'ENRUTADOR (ROUTER)',
                'ip_phone' => 'TELEFONÍA VOIP',
                'printer' => 'IMPRESORA DE RED',
                'firewall' => 'FIREWALL / GATEWAY',
                default => strtoupper($a->type_label),
            };

            $rows[] = [
                'rack' => $a->location_detail ?: 'RACK 01',
                'brand' => $a->brand ?: 'CISCO',
                'model' => $a->model ?: 'N/A',
                'serial' => $a->serial_number ?: 'N/A',
                'year' => $a->purchase_date ? $a->purchase_date->format('Y') : '2014',
                'ram' => $ram,
                'disk' => $disk,
                'os' => $a->operating_system ?: 'FIRMWARE / IOS',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => $hardware,
                'cpu' => $cpu,
                'services' => $a->notes ?: 'RED WAN / LAN',
                'ip' => $a->ip_address ?: 'N/A',
                'mac' => $a->mac_address ?: 'N/A',
                'status' => $a->status_label,
                'admin' => $a->custodian_name ?: 'ADMINISTRADOR DE RED',
            ];
            if ($a->ip_address) {
                $seenIps[$a->ip_address] = true;
            }
        }

        $netDevices = MonitoredNetworkDevice::all();
        foreach ($netDevices as $nd) {
            if ($nd->ip && isset($seenIps[$nd->ip])) {
                continue;
            }
            if (stripos($nd->name, 'Servidor') !== false || stripos($nd->name, 'MONITOREO ATIT') !== false || stripos($nd->name, 'CENCARATIT') !== false) {
                continue;
            }
            if (stripos($nd->model ?? '', 'Estación') !== false || stripos($nd->model ?? '', 'EliteDesk') !== false || stripos($nd->model ?? '', 'ThinkCentre') !== false) {
                continue;
            }

            $rows[] = [
                'rack' => 'RACK 01',
                'brand' => $nd->vendor_data ?: 'CISCO',
                'model' => $nd->model ?: 'DISPOSITIVO DE RED',
                'serial' => $nd->serial ?: 'N/A',
                'year' => '2014',
                'ram' => 'N/A',
                'disk' => 'N/A',
                'os' => 'FIRMWARE / IOS',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => 'DISPOSITIVO DE RED',
                'cpu' => 'INTEGRADO',
                'services' => $nd->notes ?: 'CONECTIVIDAD NOC',
                'ip' => $nd->ip ?: 'N/A',
                'mac' => $nd->mac ?: 'N/A',
                'status' => $nd->is_active ? 'OPERATIVO' : 'DAÑADO',
                'admin' => 'ADMINISTRADOR DE RED',
            ];
            if ($nd->ip) {
                $seenIps[$nd->ip] = true;
            }
        }

        return $rows;
    }

    /**
     * Retorna los registros normalizados para la hoja 'Centro de Datos' (Servidores + Telecomunicaciones).
     */
    public function getCenterDataRows(): array
    {
        return array_merge($this->getServerRows(), $this->getTelecomRows());
    }

    /**
     * Retorna los registros normalizados para la hoja 'Estaciones de Trabajo'.
     */
    public function getWorkstationRows(): array
    {
        $assets = InventoryAsset::with(['site', 'components', 'peripherals', 'activeAssignment'])
            ->whereIn('type', ['workstation', 'desktop', 'laptop', 'all_in_one'])
            ->where('name', 'not like', '%Servidor%')
            ->where('name', 'not like', '%CENCARATIT%')
            ->where('name', 'not like', '%PFsense%')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($assets as $a) {
            $responsable = $a->activeAssignment?->custodian_name ?: ($a->custodian_name ?: 'NO ASIGNADO');
            $cedula = $a->activeAssignment?->custodian_id_number ?: ($a->custodian_id_number ?: 'N/A');
            $telefono = $a->activeAssignment?->phone_extension ?: 'N/A';
            $estado = $a->site?->name ?: 'CARABOBO';
            $unidad = $a->activeAssignment?->custodian_department ?: ($a->custodian_department ?: 'OPERACIONES');

            $ram = $a->components->where('type', 'ram')->first()?->capacity ?: '4';
            $disk = $a->components->where('type', 'disk')->first()?->capacity ?: '500GB';
            $os = $a->operating_system ?: 'XUBUNTU';

            // Fila principal de la Estación de Trabajo
            $rows[] = [
                'type' => strtoupper($a->type_label),
                'brand' => $a->brand ?: 'HP',
                'model' => $a->model ?: 'ELITEDESK',
                'serial' => $a->serial_number ?: 'N/A',
                'year' => $a->purchase_date ? $a->purchase_date->format('Y') : '2014',
                'ram' => $ram,
                'disk' => $disk,
                'os' => $os,
                'apps' => 'SAP/OFFICE',
                'custodian' => $responsable,
                'id_number' => $cedula,
                'phone' => $telefono,
                'state' => $estado,
                'unit' => $unidad,
                'status' => $a->status_label,
                'observation' => $a->notes ?: 'VALIDAR',
            ];

            // Filas consecutivas para cada periférico asociado al puesto
            foreach ($a->peripherals as $p) {
                $rows[] = [
                    'type' => strtoupper($p->type_label),
                    'brand' => $p->brand ?: 'HP',
                    'model' => $p->model ?: 'N/A',
                    'serial' => $p->serial_number ?: 'N/A',
                    'year' => '2014',
                    'ram' => 'N/A',
                    'disk' => 'N/A',
                    'os' => 'N/A',
                    'apps' => 'N/A',
                    'custodian' => $responsable,
                    'id_number' => $cedula,
                    'phone' => $telefono,
                    'state' => $estado,
                    'unit' => $unidad,
                    'status' => $p->status_label,
                    'observation' => $p->notes ?: 'VALIDAR',
                ];
            }
        }

        return $rows;
    }

    /**
     * Construye el documento SpreadsheetML XML con las dos hojas exactas del formato de inventario.
     */
    public function buildEquipmentInventorySpreadsheetXml(): string
    {
        $centerRows = $this->getCenterDataRows();
        $workstationRows = $this->getWorkstationRows();

        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        $xml .= $this->getSpreadsheetStylesXml();

        // HOJA 1: Centro de Datos
        $xml .= ' <Worksheet ss:Name="Centro de Datos">' . "\n";
        $xml .= '  <Table>' . "\n";
        $xml .= '   <Column ss:Width="60"/><Column ss:Width="80"/><Column ss:Width="110"/><Column ss:Width="100"/><Column ss:Width="50"/><Column ss:Width="60"/><Column ss:Width="60"/><Column ss:Width="80"/><Column ss:Width="50"/><Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="80"/><Column ss:Width="130"/><Column ss:Width="90"/><Column ss:Width="110"/><Column ss:Width="80"/><Column ss:Width="120"/>' . "\n";
        $xml .= '   <Row ss:Height="22"><Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">Inventario de Equipos</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="18"><Cell ss:StyleID="HeaderSubtitle"><Data ss:Type="String">Gerencia General de ATIT</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Gerencia:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">GERENCIA GENERAL DE ATIT</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Responsable Regional:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">DIVISIÓN DE OPERACIONES Y TELECOMUNICACIONES</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Estados asociados:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="24">' . "\n";
        $centerHeaders = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
        foreach ($centerHeaders as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . '</Data></Cell>' . "\n";
        }
        $xml .= '   </Row>' . "\n";
        foreach ($centerRows as $r) {
            $xml .= '   <Row ss:Height="18">' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['rack']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['physical']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['virtualized']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['hardware']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['cpu']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['services']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ip']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['mac']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['admin']) . '</Data></Cell>' . "\n";
            $xml .= '   </Row>' . "\n";
        }
        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";

        // HOJA 2: Estaciones de Trabajo
        $xml .= ' <Worksheet ss:Name="Estaciones de Trabajo">' . "\n";
        $xml .= '  <Table>' . "\n";
        $xml .= '   <Column ss:Width="110"/><Column ss:Width="70"/><Column ss:Width="100"/><Column ss:Width="110"/><Column ss:Width="50"/><Column ss:Width="60"/><Column ss:Width="60"/><Column ss:Width="90"/><Column ss:Width="110"/><Column ss:Width="130"/><Column ss:Width="75"/><Column ss:Width="85"/><Column ss:Width="80"/><Column ss:Width="110"/><Column ss:Width="80"/><Column ss:Width="90"/>' . "\n";
        $xml .= '   <Row ss:Height="22"><Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">Inventario de Equipos</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="18"><Cell ss:StyleID="HeaderSubtitle"><Data ss:Type="String">Gerencia General de ATIT</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Gerencia:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">GERENCIA GENERAL DE ATIT</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Responsable Regional:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">SOPORTE TÉCNICO REGIONAL</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Estados asociados:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="20"><Cell ss:Index="10" ss:MergeAcross="4" ss:StyleID="ColHeaderGroup"><Data ss:Type="String">ASIGNADO</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="24">' . "\n";
        $wsHeaders = ['TIPO DE EQUIPO', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'SISTEMA OPERATIVO', 'APLICATIVOS INSTALADOS', 'NOMBRE Y APELLIDO', 'CÉDULA', 'TELÉFONO', 'ESTADO', 'UNIDAD', 'CONDICIONES', 'OBSERVACION'];
        foreach ($wsHeaders as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . '</Data></Cell>' . "\n";
        }
        $xml .= '   </Row>' . "\n";
        foreach ($workstationRows as $r) {
            $xml .= '   <Row ss:Height="18">' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['type']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['apps']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['custodian']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['id_number']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['phone']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['state']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['unit']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['observation']) . '</Data></Cell>' . "\n";
            $xml .= '   </Row>' . "\n";
        }
        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";

        $xml .= '</Workbook>' . "\n";

        return $xml;
    }

    /**
     * Construye un libro Excel de una sola hoja bajo el formato Centro de Datos (Servidores o Telecomunicaciones).
     */
    public function buildSingleCategorySpreadsheetXml(string $sheetName, string $title, string $categoryDesc, array $rows): string
    {
        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        $xml .= $this->getSpreadsheetStylesXml();

        $xml .= ' <Worksheet ss:Name="' . $esc($sheetName) . '">' . "\n";
        $xml .= '  <Table>' . "\n";
        $xml .= '   <Column ss:Width="60"/><Column ss:Width="80"/><Column ss:Width="110"/><Column ss:Width="100"/><Column ss:Width="50"/><Column ss:Width="60"/><Column ss:Width="60"/><Column ss:Width="80"/><Column ss:Width="50"/><Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="80"/><Column ss:Width="130"/><Column ss:Width="90"/><Column ss:Width="110"/><Column ss:Width="80"/><Column ss:Width="120"/>' . "\n";
        $xml .= '   <Row ss:Height="22"><Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">Inventario de Equipos</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="18"><Cell ss:StyleID="HeaderSubtitle"><Data ss:Type="String">' . $esc($title) . '</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Gerencia:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">GERENCIA GENERAL DE ATIT</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Categoría:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">' . $esc($categoryDesc) . '</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Estados asociados:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="24">' . "\n";
        $headers = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
        foreach ($headers as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . '</Data></Cell>' . "\n";
        }
        $xml .= '   </Row>' . "\n";
        foreach ($rows as $r) {
            $xml .= '   <Row ss:Height="18">' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['rack']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['physical']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['virtualized']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['hardware']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['cpu']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['services']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ip']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['mac']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['admin']) . '</Data></Cell>' . "\n";
            $xml .= '   </Row>' . "\n";
        }
        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";

        $xml .= '</Workbook>' . "\n";

        return $xml;
    }

    /**
     * Construye un libro Excel de una sola hoja bajo el formato Estaciones de Trabajo.
     */
    public function buildWorkstationCategorySpreadsheetXml(array $rows): string
    {
        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        $xml .= $this->getSpreadsheetStylesXml();

        $xml .= ' <Worksheet ss:Name="Estaciones de Trabajo">' . "\n";
        $xml .= '  <Table>' . "\n";
        $xml .= '   <Column ss:Width="110"/><Column ss:Width="70"/><Column ss:Width="100"/><Column ss:Width="110"/><Column ss:Width="50"/><Column ss:Width="60"/><Column ss:Width="60"/><Column ss:Width="90"/><Column ss:Width="110"/><Column ss:Width="130"/><Column ss:Width="75"/><Column ss:Width="85"/><Column ss:Width="80"/><Column ss:Width="110"/><Column ss:Width="80"/><Column ss:Width="90"/>' . "\n";
        $xml .= '   <Row ss:Height="22"><Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">Inventario de Equipos</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="18"><Cell ss:StyleID="HeaderSubtitle"><Data ss:Type="String">Gerencia General de ATIT • Estaciones de Trabajo</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Gerencia:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">GERENCIA GENERAL DE ATIT</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Responsable Regional:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">SOPORTE TÉCNICO REGIONAL</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Estados asociados:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="20"><Cell ss:Index="10" ss:MergeAcross="4" ss:StyleID="ColHeaderGroup"><Data ss:Type="String">ASIGNADO</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="24">' . "\n";
        $wsHeaders = ['TIPO DE EQUIPO', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'SISTEMA OPERATIVO', 'APLICATIVOS INSTALADOS', 'NOMBRE Y APELLIDO', 'CÉDULA', 'TELÉFONO', 'ESTADO', 'UNIDAD', 'CONDICIONES', 'OBSERVACION'];
        foreach ($wsHeaders as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . '</Data></Cell>' . "\n";
        }
        $xml .= '   </Row>' . "\n";
        foreach ($rows as $r) {
            $xml .= '   <Row ss:Height="18">' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['type']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['apps']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['custodian']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['id_number']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['phone']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['state']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['unit']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['observation']) . '</Data></Cell>' . "\n";
            $xml .= '   </Row>' . "\n";
        }
        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";

        $xml .= '</Workbook>' . "\n";

        return $xml;
    }

    /**
     * Construye un libro Excel para reportes genéricos (IPAM, cumplimiento, etc.).
     */
    public function buildGenericReportSpreadsheetXml(string $type): string
    {
        $data = $this->getReportData($type);
        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        $xml .= $this->getSpreadsheetStylesXml();

        $sheetName = substr($data['title'], 0, 30);
        $xml .= ' <Worksheet ss:Name="' . $esc($sheetName) . '">' . "\n";
        $xml .= '  <Table>' . "\n";
        $xml .= '   <Row ss:Height="22"><Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">' . $esc($data['title']) . '</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="18"><Cell ss:StyleID="HeaderSubtitle"><Data ss:Type="String">Centro de Monitoreo &amp; Reportes NOC + ITAM</Data></Cell></Row>' . "\n";
        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Fecha de Emisión:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">' . now()->format('d/m/Y H:i:s') . '</Data></Cell></Row>' . "\n";

        if (!empty($data['summary'])) {
            $xml .= '   <Row ss:Height="12"></Row>' . "\n";
            foreach ($data['summary'] as $label => $val) {
                $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">' . $esc($label) . ':</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">' . $esc((string)$val) . '</Data></Cell></Row>' . "\n";
            }
        }

        $xml .= '   <Row ss:Height="12"></Row>' . "\n";
        $xml .= '   <Row ss:Height="24">' . "\n";
        foreach ($data['headers'] as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . '</Data></Cell>' . "\n";
        }
        $xml .= '   </Row>' . "\n";

        foreach ($data['rows'] as $r) {
            $xml .= '   <Row ss:Height="18">' . "\n";
            foreach ($r as $val) {
                $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc((string)$val) . '</Data></Cell>' . "\n";
            }
            $xml .= '   </Row>' . "\n";
        }

        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";
        $xml .= '</Workbook>' . "\n";

        return $xml;
    }

    /**
     * Retorna el bloque XML con los estilos compartidos para SpreadsheetML.
     */
    protected function getSpreadsheetStylesXml(): string
    {
        $xml = ' <Styles>' . "\n";
        $xml .= '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#000000"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="HeaderTitle"><Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#0F172A"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="HeaderSubtitle"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0284C7"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="MetaLabel"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#475569"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="MetaValue"><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#0F172A"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="ColHeader"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/></Borders><Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="ColHeaderGroup"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/></Borders><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0369A1" ss:Pattern="Solid"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="DataCell"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#0F172A"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="DataCellCenter"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#0F172A"/></Style>' . "\n";
        $xml .= ' </Styles>' . "\n";

        return $xml;
    }
}
