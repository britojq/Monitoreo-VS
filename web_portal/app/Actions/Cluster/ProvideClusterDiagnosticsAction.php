<?php

declare(strict_types=1);

namespace App\Actions\Cluster;

use App\Contracts\ClusterAuditLoggerInterface;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\NetworkTopologyLink;
use App\Models\SnmpDevice;
use App\Models\User;
use App\Services\ClusterConfigService;
use App\Services\System\SonarPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvideClusterDiagnosticsAction
{
    public function __construct(
        protected ClusterConfigService $clusterService,
        protected ClusterAuditLoggerInterface $auditLogger
    ) {}

    public function diagnostics(Request $request): JsonResponse
    {
        $baseDir = SonarPathService::baseDir();
        $storageJsonPath = storage_path('app/last_deployment.json');

        // 1. Cargar manifiesto del último despliegue si existe en storage
        $lastDeployment = null;
        if (file_exists($storageJsonPath)) {
            $lastDeployment = json_decode((string) @file_get_contents($storageJsonPath), true);
        }

        // 2. Extraer telemetría del último despliegue desde MariaDB SSOT (audit_logs)
        $logSnippet = '';
        $deploymentAudit = AuditLog::where('event', 'DEPLOYMENT')
            ->latest()
            ->first();

        if ($deploymentAudit) {
            $newValues = $deploymentAudit->new_values;
            $rawText = null;
            if (is_array($newValues)) {
                $rawText = $newValues['log_text'] ?? $newValues['log_snippet'] ?? null;
                if (!$lastDeployment && isset($newValues['manifest']) && is_array($newValues['manifest'])) {
                    $lastDeployment = $newValues['manifest'];
                }
            } elseif (is_string($newValues)) {
                $decoded = json_decode($newValues, true);
                if (is_array($decoded)) {
                    $rawText = $decoded['log_text'] ?? $decoded['log_snippet'] ?? null;
                    if (!$lastDeployment && isset($decoded['manifest']) && is_array($decoded['manifest'])) {
                        $lastDeployment = $decoded['manifest'];
                    }
                } else {
                    $rawText = $newValues;
                }
            }

            if (!empty($rawText)) {
                $lines = explode("\n", (string) $rawText);
                $logSnippet = implode("\n", array_slice($lines, -35));
            } else {
                $logSnippet = (string) ($deploymentAudit->description ?? 'Despliegue registrado en MariaDB SSOT.');
            }
        }

        // 3. Censo en tiempo real de entidades críticas de base de datos
        $snmpCount = SnmpDevice::count();
        $linksCount = NetworkTopologyLink::count();
        $servicesCount = MonitoredService::count();
        $devicesCount = MonitoredNetworkDevice::count();
        $sitesCount = MonitoredSite::count();
        $proxiesCount = MonitoredProxy::count();
        $usersCount = User::count();
        $firingAlerts = Alert::whereIn('status', ['firing', 'acknowledged'])->count();

        // 4. Estado de servicios del sistema (systemd)
        $services = [
            'tg-admin-bot' => trim((string) @shell_exec('systemctl is-active tg-admin-bot 2>/dev/null') ?: 'unknown'),
            'apache2' => trim((string) @shell_exec('systemctl is-active apache2 2>/dev/null') ?: 'unknown'),
            'mariadb' => trim((string) @shell_exec('systemctl is-active mariadb 2>/dev/null') ?: 'unknown'),
            'php-fpm' => trim((string) (@shell_exec('systemctl is-active php8.4-fpm 2>/dev/null') ?: (@shell_exec('systemctl is-active php8.2-fpm 2>/dev/null') ?: 'unknown'))),
        ];

        // 5. Metadatos de Git local
        $gitSafe = 'git -C ' . escapeshellarg($baseDir) . ' -c safe.directory=* ';
        $gitCommit = trim((string) @shell_exec($gitSafe . 'rev-parse --short HEAD 2>/dev/null') ?: 'unknown');
        $gitBranch = trim((string) @shell_exec($gitSafe . 'rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown');
        $gitMsg = trim((string) @shell_exec($gitSafe . 'log -1 --format=%s 2>/dev/null') ?: '');
        $gitDate = trim((string) @shell_exec($gitSafe . 'log -1 --format=%cd --date=iso 2>/dev/null') ?: '');

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

    public function auditTrail(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 50)));
        $logs = AuditLog::where('module', 'Cluster API')
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();

        $this->auditLogger->logAccess(
            $request,
            'cluster_audit_trail_view',
            'Consulta de bitácora forense de clúster desde ' . ($request->ip() ?: 'desconocido'),
            ['limit' => $limit, 'count' => $logs->count()]
        );

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'logs' => $logs,
        ]);
    }
}
