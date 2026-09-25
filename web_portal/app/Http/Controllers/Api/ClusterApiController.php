<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoringSnapshot;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ClusterApiController extends Controller
{
    protected ClusterConfigService $clusterService;

    public function __construct(ClusterConfigService $clusterService)
    {
        $this->clusterService = $clusterService;
    }

    /**
     * Comprueba la conectividad y validez de credenciales entre Master y Slave.
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'node_role' => $this->clusterService->getNodeRole(),
            'timestamp' => date('Y-m-d H:i:s'),
            'hostname' => gethostname(),
            'message' => 'Cluster API activa y en línea.',
        ]);
    }

    /**
     * Entrega el snapshot completo y la configuración de monitoreo al nodo Slave.
     */
    public function telemetry(Request $request): Response
    {
        $snapshotPath = storage_path('app/public/monitoring_snapshot.json');
        $snapshotPayload = null;

        if (file_exists($snapshotPath)) {
            $content = @file_get_contents($snapshotPath);
            $snapshotPayload = json_decode($content, true);
        }

        if (!$snapshotPayload) {
            $latestDb = MonitoringSnapshot::latest()->first();
            $snapshotPayload = $latestDb ? $latestDb->payload_json : [];
        }

        $payload = [
            'success' => true,
            'node_role' => $this->clusterService->getNodeRole(),
            'generated_at' => $snapshotPayload['timestamp'] ?? date('Y-m-d H:i:s'),
            'snapshot' => $snapshotPayload,
            'config' => [
                'services' => MonitoredService::orderBy('sort_order')->get(),
                'sites' => MonitoredSite::with('devices')->orderBy('sort_order')->get(),
                'proxies' => MonitoredProxy::orderBy('letter')->get(),
                'network_devices' => MonitoredNetworkDevice::orderBy('sort_order')->get(),
                'discovery_subnets' => \App\Models\DiscoverySubnet::all(),
                'snmp_oids' => \App\Models\SnmpOid::all(),
                'snmp_devices' => \App\Models\SnmpDevice::select([
                    'id', 'name', 'ip_address', 'snmp_version', 'snmp_port',
                    'snmp_timeout_seconds', 'snmp_retries', 'device_type',
                    'vendor', 'model', 'firmware_version', 'serial_number',
                    'sys_name', 'sys_description', 'sys_object_id', 'sys_uptime',
                    'sys_location', 'sys_contact', 'site_id', 'discovered_device_id',
                    'network_device_id', 'poll_interval_seconds', 'is_active',
                    'last_poll_at', 'last_poll_status', 'consecutive_failures',
                    'ssh_enabled', 'ssh_username', 'ssh_port', 'custom_oids',
                    'notes', 'created_at', 'updated_at'
                ])->get(),
                'snmp_device_oids' => \App\Models\SnmpDeviceOid::all(),
                'snmp_interfaces' => \App\Models\SnmpInterface::all(),
                'ssl_certificates' => \App\Models\SslCertificate::all(),
                'alert_rules' => \App\Models\AlertRule::all(),
                'alert_escalation_levels' => \App\Models\AlertEscalationLevel::all(),
                'alert_correlation_groups' => \App\Models\AlertCorrelationGroup::all(),
                'alert_correlation_members' => \App\Models\AlertCorrelationMember::all(),
                'maintenance_windows' => \App\Models\MaintenanceWindow::all(),
                'wol_devices' => \App\Models\WolDevice::all(),
                'hardware_lifecycle' => \App\Models\HardwareLifecycle::all(),
            ],
            'snmp_metrics_history' => \App\Models\SnmpMetricHistory::where('collected_at', '>=', now()->subHours(24))->latest('id')->take(1000)->get(),
            'snmp_interface_metrics' => \App\Models\SnmpInterfaceMetric::where('collected_at', '>=', now()->subHours(24))->latest('id')->take(1000)->get(),
            'ssl_certificate_history' => \App\Models\SslCertificateHistory::latest('id')->take(500)->get(),
            'alerts' => \App\Models\Alert::whereIn('status', ['firing', 'acknowledged', 'suppressed'])->orWhere('fired_at', '>=', now()->subHours(24))->latest('id')->take(500)->get(),
            'alert_notifications' => \App\Models\AlertNotification::latest('id')->take(200)->get(),
            'device_configurations' => \App\Models\DeviceConfiguration::select([
                'id', 'network_device_id', 'snmp_device_id', 'device_name', 'device_ip',
                'device_type', 'config_hash', 'config_size_bytes', 'captured_at', 'captured_by',
                'status', 'notes', 'created_at', 'updated_at'
            ])->latest('captured_at')->take(100)->get(),
            'config_change_logs' => \App\Models\ConfigChangeLog::latest('detected_at')->take(100)->get(),
            'audit_logs' => \App\Models\AuditLog::latest()->take(100)->get(),
            'net_radar_hosts' => \App\Models\NetRadarHost::latest('last_seen_at')->take(500)->get(),
            'net_radar_events' => \App\Models\NetRadarEvent::latest('created_at')->take(200)->get(),
            'net_radar_snapshots' => \App\Models\NetRadarSnapshot::latest('created_at')->take(50)->get(),
            'snmp_traps_received' => \App\Models\SnmpTrapReceived::latest('id')->take(200)->get(),
            'syslog_events' => \App\Models\SyslogEvent::latest('id')->take(200)->get(),
            'netflow_top_talkers' => \App\Models\NetflowTopTalker::latest('id')->take(100)->get(),
            'network_topology_links' => \App\Models\NetworkTopologyLink::all(),
            'predictive_anomalies' => \App\Models\PredictiveAnomaly::latest('id')->take(100)->get(),
            'snmp_metric_hourly_rollups' => \App\Models\SnmpMetricHourlyRollup::where('hour_timestamp', '>=', now()->subDays(30))->latest('id')->take(1000)->get(),
            'snmp_interface_hourly_rollups' => \App\Models\SnmpInterfaceHourlyRollup::where('hour_timestamp', '>=', now()->subDays(30))->latest('id')->take(1000)->get(),
        ];

        $acceptGzip = $request->boolean('gzip') || str_contains($request->header('Accept-Encoding', ''), 'gzip');
        if ($acceptGzip && function_exists('gzencode')) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            return response(gzencode($json, 6), 200, [
                'Content-Type' => 'application/json',
                'Content-Encoding' => 'gzip',
            ]);
        }

        return response()->json($payload);
    }

    /**
     * Entrega diagnóstico completo del sistema, censo de base de datos,
     * estado de servicios y auditoría del último despliegue (Solo Lectura, protegido).
     */
    public function diagnostics(Request $request): JsonResponse
    {
        $baseDir = is_dir('/scripts/telegram-admin-bot') ? '/scripts/telegram-admin-bot' : base_path('..');
        $auditJsonPath = $baseDir . '/audit/last_deployment.json';
        $storageJsonPath = storage_path('app/last_deployment.json');
        $auditLogPath = $baseDir . '/logs/last_deploy_audit.log';
        $pipelineLogPath = $baseDir . '/logs/deploy_pipeline.log';

        // 1. Cargar manifiesto del último despliegue si existe
        $lastDeployment = null;
        if (file_exists($auditJsonPath)) {
            $lastDeployment = json_decode(@file_get_contents($auditJsonPath), true);
        } elseif (file_exists($storageJsonPath)) {
            $lastDeployment = json_decode(@file_get_contents($storageJsonPath), true);
        }

        // 2. Extraer fragmento reciente del log de auditoría
        $logSnippet = '';
        $targetLog = file_exists($auditLogPath) ? $auditLogPath : (file_exists($pipelineLogPath) ? $pipelineLogPath : null);
        if ($targetLog) {
            $lines = @file($targetLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $logSnippet = implode("\n", array_slice($lines, -35));
        }

        // 3. Censo en tiempo real de entidades críticas de base de datos
        $snmpCount = \App\Models\SnmpDevice::count();
        $linksCount = \App\Models\NetworkTopologyLink::count();
        $servicesCount = \App\Models\MonitoredService::count();
        $devicesCount = \App\Models\MonitoredNetworkDevice::count();
        $sitesCount = \App\Models\MonitoredSite::count();
        $proxiesCount = \App\Models\MonitoredProxy::count();
        $usersCount = \App\Models\User::count();
        $firingAlerts = \App\Models\Alert::whereIn('status', ['firing', 'acknowledged'])->count();

        // 4. Estado de servicios del sistema (systemd)
        $services = [
            'tg-admin-bot' => trim(@shell_exec('systemctl is-active tg-admin-bot 2>/dev/null') ?: 'unknown'),
            'apache2' => trim(@shell_exec('systemctl is-active apache2 2>/dev/null') ?: 'unknown'),
            'mariadb' => trim(@shell_exec('systemctl is-active mariadb 2>/dev/null') ?: 'unknown'),
            'php-fpm' => trim(@shell_exec('systemctl is-active php8.4-fpm 2>/dev/null') ?: (@shell_exec('systemctl is-active php8.2-fpm 2>/dev/null') ?: 'unknown')),
        ];

        // 5. Metadatos de Git local
        $gitSafe = 'git -C ' . escapeshellarg($baseDir) . ' -c safe.directory=* ';
        $gitCommit = trim(@shell_exec($gitSafe . 'rev-parse --short HEAD 2>/dev/null') ?: 'unknown');
        $gitBranch = trim(@shell_exec($gitSafe . 'rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown');
        $gitMsg = trim(@shell_exec($gitSafe . 'log -1 --format=%s 2>/dev/null') ?: '');
        $gitDate = trim(@shell_exec($gitSafe . 'log -1 --format=%cd --date=iso 2>/dev/null') ?: '');

        // 6. Evaluación de salud general
        $isServicesOk = ($services['apache2'] === 'active' && $services['mariadb'] === 'active');
        $isTopologyOk = ($linksCount >= 26);
        $isSnmpOk = ($snmpCount >= 9);

        $healthStatus = 'healthy';
        if (!$isServicesOk) {
            $healthStatus = 'critical';
        } elseif (!$isTopologyOk || !$isSnmpOk) {
            $healthStatus = 'warning';
        }

        return response()->json([
            'success' => true,
            'node_role' => $this->clusterService->getNodeRole(),
            'hostname' => gethostname(),
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_health' => $healthStatus,
            'git' => [
                'commit' => $gitCommit,
                'branch' => $gitBranch,
                'last_commit_msg' => $gitMsg,
                'last_commit_date' => $gitDate,
            ],
            'database_census' => [
                'snmp_devices' => $snmpCount,
                'snmp_status' => $isSnmpOk ? 'ok' : 'incomplete',
                'network_topology_links' => $linksCount,
                'topology_status' => $isTopologyOk ? 'ok' : 'degraded',
                'monitored_services' => $servicesCount,
                'monitored_network_devices' => $devicesCount,
                'monitored_sites' => $sitesCount,
                'monitored_proxies' => $proxiesCount,
                'users' => $usersCount,
                'active_alerts' => $firingAlerts,
            ],
            'services' => $services,
            'last_deployment' => $lastDeployment,
            'audit_log_snippet' => $logSnippet,
        ]);
    }
}


