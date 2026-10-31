<?php

declare(strict_types=1);

namespace App\Actions\Cluster;

use App\Models\Alert;
use App\Models\AlertCorrelationGroup;
use App\Models\AlertCorrelationMember;
use App\Models\AlertEscalationLevel;
use App\Models\AlertNotification;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\BotCommand;
use App\Models\BotMessageTemplate;
use App\Models\ConfigChangeLog;
use App\Models\DeviceConfiguration;
use App\Models\DiscoveredDevice;
use App\Models\DiscoverySubnet;
use App\Models\HardwareLifecycle;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponent;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use App\Models\MaintenanceTicket;
use App\Models\MaintenanceWindow;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoringSnapshot;
use App\Models\NetflowTopTalker;
use App\Models\NetRadarEvent;
use App\Models\NetRadarHost;
use App\Models\NetRadarSnapshot;
use App\Models\NetworkTopologyLink;
use App\Models\PredictiveAnomaly;
use App\Models\SnmpActivationLog;
use App\Models\SnmpDevice;
use App\Models\SnmpDeviceOid;
use App\Models\SnmpInterface;
use App\Models\SnmpInterfaceMetric;
use App\Models\SnmpInterfaceHourlyRollup;
use App\Models\SnmpMetricHistory;
use App\Models\SnmpMetricHourlyRollup;
use App\Models\SnmpOid;
use App\Models\SnmpTrapReceived;
use App\Models\SslCertificate;
use App\Models\SslCertificateHistory;
use App\Models\SyslogEvent;
use App\Models\UpsDevice;
use App\Models\UpsPowerEvent;
use App\Models\UpsTelemetryHistory;
use App\Models\WolDevice;
use App\Contracts\ClusterAuditLoggerInterface;
use App\Services\ClusterConfigService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProvideClusterTelemetryAction
{
    public function __construct(
        protected ClusterConfigService $clusterService,
        protected ClusterAuditLoggerInterface $auditLogger
    ) {}

    public function execute(Request $request): Response
    {
        $snapshotPath = storage_path('app/public/monitoring_snapshot.json');
        $snapshotPayload = null;

        if (file_exists($snapshotPath)) {
            $content = @file_get_contents($snapshotPath);
            $snapshotPayload = json_decode((string) $content, true);
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
                'discovery_subnets' => DiscoverySubnet::all(),
                'discovered_devices' => DiscoveredDevice::all(),
                'bot_commands' => BotCommand::all(),
                'bot_message_templates' => BotMessageTemplate::all(),
                'snmp_oids' => SnmpOid::all(),
                'snmp_devices' => SnmpDevice::select([
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
                'snmp_device_oids' => SnmpDeviceOid::all(),
                'snmp_interfaces' => SnmpInterface::all(),
                'ssl_certificates' => SslCertificate::all(),
                'alert_rules' => AlertRule::all(),
                'alert_escalation_levels' => AlertEscalationLevel::all(),
                'alert_correlation_groups' => AlertCorrelationGroup::all(),
                'alert_correlation_members' => AlertCorrelationMember::all(),
                'maintenance_windows' => MaintenanceWindow::all(),
                'wol_devices' => WolDevice::all(),
                'hardware_lifecycle' => HardwareLifecycle::all(),
                'ups_devices' => UpsDevice::all(),
                'inventory_assets' => InventoryAsset::all(),
                'inventory_components' => InventoryComponent::all(),
                'inventory_peripherals' => InventoryPeripheral::all(),
                'inventory_assignments' => InventoryAssignment::all(),
                'maintenance_tickets' => MaintenanceTicket::all(),
            ],
            'snmp_metrics_history' => SnmpMetricHistory::where('collected_at', '>=', now()->subHours(24))->latest('id')->take(1000)->get(),
            'snmp_interface_metrics' => SnmpInterfaceMetric::where('collected_at', '>=', now()->subHours(24))->latest('id')->take(1000)->get(),
            'ssl_certificate_history' => SslCertificateHistory::latest('id')->take(500)->get(),
            'alerts' => Alert::whereIn('status', ['firing', 'acknowledged', 'suppressed'])->orWhere('fired_at', '>=', now()->subHours(24))->latest('id')->take(500)->get(),
            'alert_notifications' => AlertNotification::latest('id')->take(200)->get(),
            'device_configurations' => DeviceConfiguration::select([
                'id', 'network_device_id', 'snmp_device_id', 'device_name', 'device_ip',
                'device_type', 'config_hash', 'config_size_bytes', 'captured_at', 'captured_by',
                'status', 'notes', 'created_at', 'updated_at'
            ])->latest('captured_at')->take(100)->get(),
            'config_change_logs' => ConfigChangeLog::latest('detected_at')->take(100)->get(),
            'audit_logs' => AuditLog::latest()->take(100)->get(),
            'net_radar_hosts' => NetRadarHost::latest('last_seen_at')->take(500)->get(),
            'net_radar_events' => NetRadarEvent::latest('created_at')->take(200)->get(),
            'net_radar_snapshots' => NetRadarSnapshot::latest('created_at')->take(50)->get(),
            'snmp_traps_received' => SnmpTrapReceived::latest('id')->take(200)->get(),
            'syslog_events' => SyslogEvent::latest('id')->take(200)->get(),
            'netflow_top_talkers' => NetflowTopTalker::latest('id')->take(100)->get(),
            'network_topology_links' => NetworkTopologyLink::all(),
            'predictive_anomalies' => PredictiveAnomaly::latest('id')->take(100)->get(),
            'snmp_metric_hourly_rollups' => SnmpMetricHourlyRollup::where('hour_timestamp', '>=', now()->subDays(30))->latest('id')->take(1000)->get(),
            'snmp_interface_hourly_rollups' => SnmpInterfaceHourlyRollup::where('hour_timestamp', '>=', now()->subDays(30))->latest('id')->take(1000)->get(),
            'ups_telemetry_histories' => UpsTelemetryHistory::where('recorded_at', '>=', now()->subHours(24))->latest('id')->take(1000)->get(),
            'ups_power_events' => UpsPowerEvent::latest('id')->take(200)->get(),
            'inventory_components_histories' => InventoryComponentHistory::latest('id')->take(500)->get(),
        ];

        $this->auditLogger->logAccess(
            $request,
            'cluster_telemetry_sync',
            'Sincronización de telemetría y catálogo solicitada desde ' . ($request->ip() ?: 'desconocido'),
            ['node_role' => $this->clusterService->getNodeRole()]
        );

        $acceptGzip = $request->boolean('gzip') || str_contains($request->header('Accept-Encoding', ''), 'gzip');
        if ($acceptGzip && function_exists('gzencode')) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            return response(gzencode((string) $json, 6), 200, [
                'Content-Type' => 'application/json',
                'Content-Encoding' => 'gzip',
            ]);
        }

        return response()->json($payload);
    }
}
