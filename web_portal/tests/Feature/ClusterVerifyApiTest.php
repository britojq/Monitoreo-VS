<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\ClusterConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClusterVerifyApiTest extends TestCase
{
    use DatabaseTransactions;

    protected string $clusterToken;

    protected function setUp(): void
    {
        parent::setUp();
        $clusterService = new ClusterConfigService();
        $this->clusterToken = $clusterService->getClusterToken();
    }

    /**
     * Test 1: Solicitud sin token debe responder 404 (Ocultamiento de endpoint).
     */
    public function test_verify_endpoint_without_token_returns_404(): void
    {
        $response = $this->getJson(route('api.cluster.verify'));
        $response->assertStatus(404);
    }

    /**
     * Test 2: Solicitud con token inválido debe responder 404.
     */
    public function test_verify_endpoint_with_invalid_token_returns_404(): void
    {
        $response = $this->getJson(route('api.cluster.verify'), [
            'X-Cluster-Token' => 'token_totalmente_falso_1234567890',
        ]);
        $response->assertStatus(404);
    }

    /**
     * Test 3: Solicitud con token válido retorna estructura completa y código 200.
     */
    public function test_verify_endpoint_with_valid_token_returns_full_health_structure(): void
    {
        $response = $this->getJson(route('api.cluster.verify'), [
            'X-Cluster-Token' => $this->clusterToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'node_role',
            'hostname',
            'scope',
            'status',
            'timestamp',
            'summary' => [
                'core_services_ok',
                'all_installers_present',
                'migrations_synced',
                'git_is_clean',
                'glpi_active_agents',
            ],
            'installers' => [
                'directory_path',
                'total_tracked',
                'present_count',
                'all_present',
                'files',
            ],
            'glpi_agents' => [
                'total_inventory_assets',
                'glpi_installed_total',
                'glpi_reporting_24h',
                'glpi_offline_count',
                'adoption_percentage',
                'breakdown_by_os',
                'recent_agents',
                'receiver_endpoint',
                'token_tag',
            ],
            'runtime_parity' => [
                'platform' => [
                    'system_version',
                    'laravel_version',
                    'php_version',
                    'hostname',
                ],
                'git' => [
                    'commit_short',
                    'branch',
                    'is_clean',
                ],
                'database_census',
                'migrations' => [
                    'applied_count',
                    'files_count',
                    'is_synchronized',
                ],
                'system_services',
                'port_listeners',
                'resources',
            ],
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('all', $response->json('scope'));
    }

    /**
     * Test 4: Scope installers retorna exclusivamente información de binarios.
     */
    public function test_verify_endpoint_scoped_to_installers(): void
    {
        $response = $this->getJson(route('api.cluster.verify', ['scope' => 'installers']), [
            'X-Cluster-Token' => $this->clusterToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('scope', 'installers');
        $this->assertArrayHasKey('installers', $response->json());
        $this->assertArrayNotHasKey('glpi_agents', $response->json());
    }

    /**
     * Test 5: Toda consulta registra una traza forense obligatoria en audit_logs.
     */
    public function test_verify_endpoint_automatically_records_immutable_audit_log(): void
    {
        $initialCount = AuditLog::where('event', 'cluster_api_verification')->count();

        $this->getJson(route('api.cluster.verify', ['scope' => 'agents']), [
            'X-Cluster-Token' => $this->clusterToken,
        ]);

        $newCount = AuditLog::where('event', 'cluster_api_verification')->count();
        $this->assertEquals($initialCount + 1, $newCount);

        $latestLog = AuditLog::where('event', 'cluster_api_verification')->latest('id')->first();
        $this->assertNotNull($latestLog);
        $this->assertEquals('Cluster API', $latestLog->module);
        $this->assertStringContainsString('scope: agents', $latestLog->description);
    }

    /**
     * Test 6: Endpoint audit-trail retorna las bitácoras registradas de clúster.
     */
    public function test_audit_trail_endpoint_returns_cluster_logs(): void
    {
        $response = $this->getJson(route('api.cluster.audit-trail', ['limit' => 10]), [
            'X-Cluster-Token' => $this->clusterToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'logs',
        ]);
        $this->assertTrue($response->json('success'));
    }
}
