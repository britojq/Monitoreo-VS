<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Services\ClusterConfigInterface;
use App\Services\ClusterConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClusterHmacSecurityTest extends TestCase
{
    protected string $clusterToken;

    protected function setUp(): void
    {
        parent::setUp();
        $clusterService = new ClusterConfigService();
        $this->clusterToken = $clusterService->getClusterToken();
    }

    public function test_cluster_endpoint_accepts_valid_hmac_and_timestamp(): void
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
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_cluster_endpoint_rejects_expired_timestamp(): void
    {
        // Timestamp de hace más de 10 minutos (600 segundos)
        $timestamp = time() - 601;
        $signature = hash_hmac('sha256', "{$timestamp}:", $this->clusterToken);

        $response = $this->getJson(route('api.cluster.ping'), [
            'X-Cluster-Token' => $this->clusterToken,
            'X-Cluster-Timestamp' => (string) $timestamp,
            'X-Cluster-Signature' => $signature,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Timestamp Expired',
        ]);
    }

    public function test_cluster_endpoint_rejects_invalid_hmac_signature(): void
    {
        $timestamp = time();
        $forgedSignature = hash_hmac('sha256', "{$timestamp}:forged_content", 'wrong_secret_key');

        $response = $this->getJson(route('api.cluster.ping'), [
            'X-Cluster-Token' => $this->clusterToken,
            'X-Cluster-Timestamp' => (string) $timestamp,
            'X-Cluster-Signature' => $forgedSignature,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Invalid Signature',
        ]);
    }

    public function test_cluster_endpoint_rejects_missing_timestamp_or_signature(): void
    {
        // Fail-closed (F-09): Solicitud con token válido pero sin cabeceras HMAC o Timestamp responde 404
        $response = $this->getJson(route('api.cluster.ping'), [
            'X-Cluster-Token' => $this->clusterToken,
        ]);

        $response->assertStatus(404);
    }

    public function test_cluster_endpoint_rejects_query_parameter_token(): void
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}:", $this->clusterToken);

        $response = $this->getJson(route('api.cluster.ping', ['cluster_token' => $this->clusterToken]), [
            'X-Cluster-Timestamp' => (string) $timestamp,
            'X-Cluster-Signature' => $signature,
        ]);

        $response->assertStatus(404);
    }

    public function test_cluster_endpoint_rejects_replayed_signature(): void
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}:", $this->clusterToken);

        $headers = [
            'X-Cluster-Token' => $this->clusterToken,
            'X-Cluster-Timestamp' => (string) $timestamp,
            'X-Cluster-Signature' => $signature,
            'Accept' => 'application/json',
        ];

        // Primer despacho: autorizada
        $firstResponse = $this->get(route('api.cluster.ping'), $headers);
        $firstResponse->assertStatus(200);

        // Segundo despacho idéntico dentro de la ventana de 300s (Replay Attack): rechazada con 403
        $replayResponse = $this->get(route('api.cluster.ping'), $headers);
        $replayResponse->assertStatus(403);
        $replayResponse->assertJson([
            'message' => 'Signature Already Used',
        ]);
    }

    public function test_cluster_endpoint_rejects_invalid_token(): void
    {
        $response = $this->getJson(route('api.cluster.ping'), [
            'X-Cluster-Token' => 'invalid_token_xyz',
        ]);

        $response->assertStatus(404);
    }
}
