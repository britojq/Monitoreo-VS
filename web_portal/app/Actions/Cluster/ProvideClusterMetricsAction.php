<?php

declare(strict_types=1);

namespace App\Actions\Cluster;

use App\Contracts\ClusterAuditLoggerInterface;
use App\Contracts\Services\ClusterConfigInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProvideClusterMetricsAction
{
    public function __construct(
        protected ClusterConfigInterface $clusterService,
        protected ClusterAuditLoggerInterface $auditLogger
    ) {}

    public function execute(Request $request): Response
    {
        $queueMetrics = $this->collectQueueWorkerMetrics();
        $databaseMetrics = $this->collectDatabaseMetrics();
        $jobMetrics = $this->collectJobQueueMetrics();

        $this->auditLogger->logAccess(
            $request,
            'cluster_metrics_scraped',
            'Scraping de métricas de telemetría y salud de clúster desde ' . ($request->ip() ?: 'desconocido'),
            ['node_role' => $this->clusterService->getNodeRole()]
        );

        $isPrometheusFormat = $request->query('format') === 'prometheus'
            || str_contains($request->header('Accept', ''), 'text/plain');

        if ($isPrometheusFormat) {
            $prometheusText = $this->formatPrometheusOutput($queueMetrics, $databaseMetrics, $jobMetrics);
            return response($prometheusText, 200, [
                'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            ]);
        }

        return response()->json([
            'success' => true,
            'node_role' => $this->clusterService->getNodeRole(),
            'timestamp' => date('Y-m-d H:i:s'),
            'queue_worker' => $queueMetrics,
            'database' => $databaseMetrics,
            'jobs' => $jobMetrics,
        ]);
    }

    /**
     * Recopilar métricas de cgroups v2 del supervisor de colas (sonar-queue-worker.service).
     *
     * @return array<string, mixed>
     */
    protected function collectQueueWorkerMetrics(): array
    {
        $cgroupBase = '/sys/fs/cgroup/system.slice/sonar-queue-worker.service';
        $memCurrentPath = "{$cgroupBase}/memory.current";
        $memHighPath = "{$cgroupBase}/memory.high";
        $memMaxPath = "{$cgroupBase}/memory.max";

        $currentBytes = file_exists($memCurrentPath) ? (int) trim((string) @file_get_contents($memCurrentPath)) : 0;
        $highBytes = file_exists($memHighPath) ? (int) trim((string) @file_get_contents($memHighPath)) : 419430400; // 400M fallback
        $maxBytes = file_exists($memMaxPath) ? (int) trim((string) @file_get_contents($memMaxPath)) : 536870912; // 512M fallback

        $usagePct = $maxBytes > 0 ? round(($currentBytes / $maxBytes) * 100, 2) : 0.0;

        return [
            'service_name' => 'sonar-queue-worker.service',
            'cgroups_version' => 2,
            'memory_current_bytes' => $currentBytes,
            'memory_current_mb' => round($currentBytes / (1024 * 1024), 2),
            'memory_high_bytes' => $highBytes,
            'memory_max_bytes' => $maxBytes,
            'memory_usage_pct' => $usagePct,
            'governance_limit_mb' => round($maxBytes / (1024 * 1024), 2),
        ];
    }

    /**
     * Recopilar métricas de concurrencia y estado de MariaDB.
     *
     * @return array<string, int>
     */
    protected function collectDatabaseMetrics(): array
    {
        $metrics = [
            'threads_connected' => 0,
            'threads_running' => 0,
            'uptime_seconds' => 0,
        ];

        try {
            $results = DB::select("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Uptime')");
            foreach ($results as $row) {
                if ($row->Variable_name === 'Threads_connected') {
                    $metrics['threads_connected'] = (int) $row->Value;
                } elseif ($row->Variable_name === 'Threads_running') {
                    $metrics['threads_running'] = (int) $row->Value;
                } elseif ($row->Variable_name === 'Uptime') {
                    $metrics['uptime_seconds'] = (int) $row->Value;
                }
            }
        } catch (\Throwable) {
            // Degeneración elegante si la BD no permite SHOW STATUS
        }

        return $metrics;
    }

    /**
     * Recopilar contadores de colas en MariaDB.
     *
     * @return array<string, int>
     */
    protected function collectJobQueueMetrics(): array
    {
        $pending = 0;
        $failed = 0;

        try {
            $pending = DB::table('jobs')->count();
        } catch (\Throwable) {
            // Tabla jobs no existe o no accesible
        }

        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            // Tabla failed_jobs no existe o no accesible
        }

        return [
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
        ];
    }

    /**
     * Formatear métricas en formato estándar Prometheus (OpenMetrics).
     *
     * @param array<string, mixed> $queue
     * @param array<string, int> $db
     * @param array<string, int> $jobs
     */
    protected function formatPrometheusOutput(array $queue, array $db, array $jobs): string
    {
        $role = $this->clusterService->getNodeRole();
        $lines = [
            '# HELP sonar_queue_worker_memory_bytes Uso de memoria RAM actual en cgroups v2 del supervisor de colas',
            '# TYPE sonar_queue_worker_memory_bytes gauge',
            "sonar_queue_worker_memory_bytes{node_role=\"{$role}\"} {$queue['memory_current_bytes']}",
            '',
            '# HELP sonar_queue_worker_memory_max_bytes Limite duro MemoryMax de cgroups v2',
            '# TYPE sonar_queue_worker_memory_max_bytes gauge',
            "sonar_queue_worker_memory_max_bytes{node_role=\"{$role}\"} {$queue['memory_max_bytes']}",
            '',
            '# HELP sonar_queue_pending_jobs Total de trabajos asincronos pendientes en la cola',
            '# TYPE sonar_queue_pending_jobs gauge',
            "sonar_queue_pending_jobs{node_role=\"{$role}\"} {$jobs['pending_jobs']}",
            '',
            '# HELP sonar_queue_failed_jobs Total de trabajos asincronos fallidos en la cola',
            '# TYPE sonar_queue_failed_jobs counter',
            "sonar_queue_failed_jobs{node_role=\"{$role}\"} {$jobs['failed_jobs']}",
            '',
            '# HELP sonar_mariadb_threads_connected Conexiones de clientes activas a MariaDB',
            '# TYPE sonar_mariadb_threads_connected gauge',
            "sonar_mariadb_threads_connected{node_role=\"{$role}\"} {$db['threads_connected']}",
            '',
            '# HELP sonar_mariadb_threads_running Hilos ejecutandose concurrentemente en MariaDB',
            '# TYPE sonar_mariadb_threads_running gauge',
            "sonar_mariadb_threads_running{node_role=\"{$role}\"} {$db['threads_running']}",
            '',
        ];

        return implode("\n", $lines) . "\n";
    }
}
