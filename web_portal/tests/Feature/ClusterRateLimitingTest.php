<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ClusterConfigService;
use Tests\TestCase;

class ClusterRateLimitingTest extends TestCase
{
    protected string $clusterToken;

    protected function setUp(): void
    {
        parent::setUp();
        $clusterService = new ClusterConfigService();
        $this->clusterToken = $clusterService->getClusterToken();
    }

    public function test_cluster_endpoints_enforce_rate_limiting_headers(): void
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}:", $this->clusterToken);

        $response = $this->get(route('api.cluster.ping'), [
            'X-Cluster-Token' => $this->clusterToken,
            'X-Cluster-Timestamp' => (string) $timestamp,
            'X-Cluster-Signature' => $signature,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit') || $response->headers->has('RateLimit-Limit'),
            'La API de Clúster debe retornar cabeceras de Rate Limiting (SEC-008)'
        );
    }

    public function test_composer_json_restricts_allow_plugins(): void
    {
        $composerPath = base_path('composer.json');
        $this->assertFileExists($composerPath);

        $composerData = json_decode(file_get_contents($composerPath), true);
        $this->assertIsArray($composerData);
        $this->assertArrayHasKey('config', $composerData);
        $this->assertArrayHasKey('allow-plugins', $composerData['config']);

        $allowPlugins = $composerData['config']['allow-plugins'];
        $this->assertEmpty($allowPlugins, 'allow-plugins debe estar vacío o estrictamente restringido (SEC-011)');
    }
}
