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
    public function telemetry(Request $request): JsonResponse
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

        return response()->json([
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
        ]);
    }
}

