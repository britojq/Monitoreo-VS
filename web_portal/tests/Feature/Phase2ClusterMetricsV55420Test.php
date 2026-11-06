<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ClusterConfigService;
use Tests\TestCase;

class Phase2ClusterMetricsV55420Test extends TestCase
{
    protected string $clusterToken;

    protected function setUp(): void
    {
        parent::setUp();
        $clusterService = new ClusterConfigService();
        $this->clusterToken = $clusterService->getClusterToken();
    }

    /**
     * Construir cabeceras firmadas con HMAC válidas para el middleware cluster.token.
     *
     * @param array<string, string> $extraHeaders
     * @return array<string, string>
     */
    protected function getSignedClusterHeaders(array $extraHeaders = []): array
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}:", $this->clusterToken);

        return array_merge([
            'X-Cluster-Token' => $this->clusterToken,
            'X-Cluster-Timestamp' => (string) $timestamp,
            'X-Cluster-Signature' => $signature,
            'Accept' => 'application/json',
        ], $extraHeaders);
    }

    /**
     * TECH-OPS-03: Verificar que el endpoint de telemetría y métricas rechace
     * solicitudes no autenticadas (Fail-Closed, 404 Not Found conforme a la arquitectura).
     */
    public function test_metrics_endpoint_rejects_unauthenticated_request(): void
    {
        $response = $this->get(route('api.cluster.metrics'));
        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * TECH-OPS-03: Verificar que con credenciales válidas el endpoint retorne
     * las métricas de salud en JSON con las dimensiones de colas, memoria cgroups y MariaDB.
     */
    public function test_metrics_endpoint_returns_json_with_valid_cluster_hmac(): void
    {
        $headers = $this->getSignedClusterHeaders();
        $response = $this->get(route('api.cluster.metrics'), $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'node_role',
            'timestamp',
            'queue_worker' => [
                'service_name',
                'cgroups_version',
                'memory_current_bytes',
                'memory_current_mb',
                'memory_high_bytes',
                'memory_max_bytes',
                'memory_usage_pct',
                'governance_limit_mb',
            ],
            'database' => [
                'threads_connected',
                'threads_running',
                'uptime_seconds',
            ],
            'jobs' => [
                'pending_jobs',
                'failed_jobs',
            ],
        ]);

        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertSame('sonar-queue-worker.service', $json['queue_worker']['service_name']);
        $this->assertSame(536870912, $json['queue_worker']['memory_max_bytes']);
        $this->assertIsInt($json['jobs']['pending_jobs']);
        $this->assertIsInt($json['jobs']['failed_jobs']);
        $this->assertIsInt($json['database']['threads_connected']);
    }

    /**
     * TECH-OPS-03: Verificar que al solicitar formato Prometheus (format=prometheus o text/plain)
     * el endpoint entregue telemetría en formato estándar OpenMetrics.
     */
    public function test_metrics_endpoint_returns_prometheus_format_when_requested(): void
    {
        $headers = $this->getSignedClusterHeaders([
            'Accept' => 'text/plain',
        ]);

        $response = $this->get(route('api.cluster.metrics', ['format' => 'prometheus']), $headers);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('sonar_queue_worker_memory_bytes', $content);
        $this->assertStringContainsString('sonar_queue_worker_memory_max_bytes', $content);
        $this->assertStringContainsString('sonar_queue_pending_jobs', $content);
        $this->assertStringContainsString('sonar_queue_failed_jobs', $content);
        $this->assertStringContainsString('sonar_mariadb_threads_connected', $content);
    }

    /**
     * Garantía de Inmutabilidad Criptográfica: Verificar que el archivo de auditoría
     * docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md se mantenga intacto.
     */
    public function test_audit_report_hash_remains_strictly_immutable(): void
    {
        $auditReport = base_path('../docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md');
        $this->assertFileExists($auditReport);

        $expectedHash = '88d8174095afbe98c7ad47e6c9cd86e9bc5c1d3c6690d13de9d52fff9f6cd165';
        $actualHash = hash_file('sha256', $auditReport);

        $this->assertSame(
            $expectedHash,
            $actualHash,
            'CRÍTICO: El archivo de auditoría ha sido alterado o manipulado.'
        );
    }
}
