<?php

namespace Tests\Feature;

use App\Contracts\NetworkTopologyInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\SnmpDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NetworkTopologyTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $operatorUser;
    protected User $unauthorizedUser;
    protected MonitoredSite $testSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_topology_test@valle-seco.local'],
            [
                'name' => 'Admin Topology Tester',
                'role' => 'admin',
                'is_active' => true,
                'terms_accepted' => true,
                'password' => bcrypt('password123'),
            ]
        );
        $this->adminUser->update(['is_active' => true]);

        $this->operatorUser = User::firstOrCreate(
            ['email' => 'operator_topology_test@valle-seco.local'],
            [
                'name' => 'Operador Topology Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => ['topology.view'],
                'password' => bcrypt('password123'),
            ]
        );
        $this->operatorUser->update(['is_active' => true]);

        $this->unauthorizedUser = User::firstOrCreate(
            ['email' => 'unauth_topology_test@valle-seco.local'],
            [
                'name' => 'Usuario No Autorizado Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => [], // Sin permisos
                'password' => bcrypt('password123'),
            ]
        );
        $this->unauthorizedUser->update(['is_active' => true]);

        $this->testSite = MonitoredSite::firstOrCreate(
            ['name' => 'SEDE TEST TOPOLOGIA'],
            [
                'ip' => '10.20.95.1',
                'is_active' => true,
                'sort_order' => 99,
            ]
        );
    }

    public function test_admin_and_authorized_operator_can_access_topology_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.topology.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.topology.index');
        $response->assertSee('Topología de Red Visual Dinámica');
        $response->assertSee('Filtrar Sede:');

        // Cumplimiento estricto de Regla de Oro #9 y Error #7
        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
        $response->assertDontSee('SLOT #');
        $response->assertDontSee('Ollama');

        // Acceso del operador autorizado
        $respOperator = $this->actingAs($this->operatorUser)->get(route('admin.topology.index'));
        $respOperator->assertStatus(200);
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.topology.index'));
        $response->assertStatus(403);
    }

    public function test_topology_data_endpoint_returns_json_graph_structure(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.topology.data'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'elements' => [
                'nodes' => [
                    '*' => [
                        'data' => [
                            'id',
                            'label',
                            'ip',
                            'type',
                            'status',
                            'vendor',
                            'model',
                        ],
                        'classes',
                    ],
                ],
                'edges' => [
                    '*' => [
                        'data' => [
                            'id',
                            'source',
                            'target',
                            'link_type',
                            'status',
                        ],
                        'classes',
                    ],
                ],
            ],
            'meta' => [
                'total_nodes',
                'total_edges',
                'generated_at',
            ],
        ]);
    }

    public function test_topology_service_integrates_itam_workstations_with_access_edges(): void
    {
        // Crear un switch SNMP efímero en la sede de prueba
        $switch = SnmpDevice::create([
            'name' => 'SW-ACCESO-TEST-PISO1',
            'ip_address' => '10.20.95.2',
            'device_type' => 'switch',
            'vendor' => 'Cisco Systems',
            'model' => 'Catalyst 2960-X',
            'site_id' => $this->testSite->id,
            'is_active' => true,
        ]);

        // Crear puesto de trabajo efímero
        $asset = InventoryAsset::create([
            'asset_tag' => 'AST-TOPO-' . uniqid(),
            'name' => 'TAQUILLA 03 RECEPTORA', // Nombre Etiqueta (SSOT Regla #7)
            'type' => 'desktop',
            'status' => 'assigned',
            'ip_address' => '10.20.95.105',
            'mac_address' => '00:15:5d:aa:bb:cc',
            'serial_number' => 'SN-TOPO-' . uniqid(),
            'brand' => 'Dell',
            'model' => 'OptiPlex 3050',
            'monitored_site_id' => $this->testSite->id,
            'custodian_name' => 'María Elena Fernández',
        ]);

        /** @var NetworkTopologyInterface $service */
        $service = app(NetworkTopologyInterface::class);
        $graph = $service->getTopologyGraph($this->testSite->id);

        $nodeIds = array_column(array_column($graph['elements']['nodes'], 'data'), 'id');
        $this->assertContains('asset_' . $asset->id, $nodeIds);

        // Verificar nodo de estación de trabajo
        $workstationNode = collect($graph['elements']['nodes'])
            ->firstWhere('data.id', 'asset_' . $asset->id);

        $this->assertNotNull($workstationNode);
        $this->assertEquals('TAQUILLA 03 RECEPTORA', $workstationNode['data']['label']);
        $this->assertEquals('workstation', $workstationNode['data']['type']);
        $this->assertEquals('10.20.95.105', $workstationNode['data']['ip']);
        $this->assertEquals('María Elena Fernández', $workstationNode['data']['responsable']);
        $this->assertStringContainsString('/admin/inventory/assets/' . $asset->id, $workstationNode['data']['device_url']);

        // Verificar enlace de acceso hacia el switch
        $accessEdge = collect($graph['elements']['edges'])
            ->firstWhere('data.target', 'asset_' . $asset->id);

        $this->assertNotNull($accessEdge);
        $this->assertEquals('snmp_' . $switch->id, $accessEdge['data']['source']);
        $this->assertEquals('ACCESS', $accessEdge['data']['link_type']);
    }

    public function test_topology_data_filters_by_site_parameter(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.topology.data', ['site_id' => $this->testSite->id]));

        $response->assertStatus(200);
        $this->assertEquals($this->testSite->id, $response->json('meta.site_id'));
    }

    public function test_topology_rebuild_triggers_audit_log_and_redirects(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.topology.rebuild'));

        $response->assertRedirect(route('admin.topology.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'topology',
            'event' => 'topology_rebuilt',
        ]);
    }
}
