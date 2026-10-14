<?php

namespace Tests\Feature;

use App\Contracts\IpamGridInterface;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IpamGridTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $operatorUser;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_ipam_test@valle-seco.local'],
            [
                'name' => 'Admin IPAM Tester',
                'role' => 'admin',
                'is_active' => true,
                'terms_accepted' => true,
                'password' => bcrypt('password123'),
            ]
        );
        $this->adminUser->update(['is_active' => true]);

        $this->operatorUser = User::firstOrCreate(
            ['email' => 'operator_ipam_test@valle-seco.local'],
            [
                'name' => 'Operador IPAM Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => ['ipam.view'],
                'password' => bcrypt('password123'),
            ]
        );
        $this->operatorUser->update(['is_active' => true]);

        $this->unauthorizedUser = User::firstOrCreate(
            ['email' => 'unauth_ipam_test@valle-seco.local'],
            [
                'name' => 'Usuario No Autorizado Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => [], // Sin permisos de red
                'password' => bcrypt('password123'),
            ]
        );
        $this->unauthorizedUser->update(['is_active' => true]);
    }

    public function test_admin_can_access_ipam_index_view(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.ipam.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.ipam.index');
        $response->assertViewHasAll(['subnets', 'selectedSubnet', 'grid']);

        // Verificar cumplimiento de Regla de Oro #9 y Error #7 (Cero términos vetados)
        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
        $response->assertDontSee('SLOT #');
    }

    public function test_operator_with_network_permission_can_access_ipam(): void
    {
        $response = $this->actingAs($this->operatorUser)->get(route('admin.ipam.index'));
        $response->assertStatus(200);
    }

    public function test_user_without_network_permission_receives_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.ipam.index'));
        $response->assertStatus(403);
    }

    public function test_ipam_grid_service_generates_256_cells_for_slash_24_subnet(): void
    {
        /** @var IpamGridInterface $service */
        $service = app(IpamGridInterface::class);
        $grid = $service->getSubnetGrid('10.20.23.0/24');

        $this->assertEquals('10.20.23.0/24', $grid['subnet']);
        $this->assertCount(256, $grid['cells']);

        // Celda .0 es Identificador de Red
        $cell0 = $grid['cells'][0];
        $this->assertEquals('10.20.23.0', $cell0['ip']);
        $this->assertEquals(0, $cell0['octet']);
        $this->assertEquals('network_id', $cell0['state']);
        $this->assertTrue($cell0['is_reserved']);

        // Celda .255 es Broadcast
        $cell255 = $grid['cells'][255];
        $this->assertEquals('10.20.23.255', $cell255['ip']);
        $this->assertEquals(255, $cell255['octet']);
        $this->assertEquals('broadcast', $cell255['state']);
        $this->assertTrue($cell255['is_reserved']);

        // Estadísticas
        $this->assertEquals(256, $grid['stats']['total']);
        $this->assertEquals(2, $grid['stats']['reserved']);
        $this->assertEquals(254, $grid['stats']['usable_capacity']);
    }

    public function test_ipam_grid_classifies_official_inventory_asset(): void
    {
        $tag = 'TEST-IPAM-' . uniqid();
        $asset = InventoryAsset::create([
            'asset_tag' => $tag,
            'name' => 'TAQUILLA 05 PRUEBA',
            'type' => 'desktop',
            'status' => 'assigned',
            'ip_address' => '10.20.23.205',
            'mac_address' => '00:11:22:33:44:aa',
            'serial_number' => 'SN-IPAM-' . uniqid(),
            'model' => 'OptiPlex 7050',
            'brand' => 'Dell',
        ]);

        /** @var IpamGridInterface $service */
        $service = app(IpamGridInterface::class);
        $grid = $service->getSubnetGrid('10.20.23.0/24');

        $cell = $grid['cells'][205] ?? null;
        $this->assertNotNull($cell);
        $this->assertEquals('10.20.23.205', $cell['ip']);
        $this->assertTrue($cell['is_official']);
        $this->assertFalse($cell['is_rogue']);
        $this->assertEquals('TAQUILLA 05 PRUEBA', $cell['display_label']);
        $this->assertEquals('inventory_asset', $cell['source']);
        $this->assertStringContainsString('official', $cell['state']);
    }

    public function test_ipam_grid_classifies_discovered_rogue_device(): void
    {
        $testMac = '02:99:88:77:66:55';
        $rogue = DiscoveredDevice::create([
            'ip_address' => '10.20.23.206',
            'mac_address' => $testMac,
            'hostname' => 'UNKNOWN-DEVICE-ROGUE',
            'vendor' => 'Unknown Brand',
            'status' => 'pending',
            'first_seen' => now(),
            'last_seen' => now(),
        ]);

        /** @var IpamGridInterface $service */
        $service = app(IpamGridInterface::class);
        $grid = $service->getSubnetGrid('10.20.23.0/24');

        $cell = $grid['cells'][206] ?? null;
        $this->assertNotNull($cell);
        $this->assertEquals('10.20.23.206', $cell['ip']);
        $this->assertEquals('rogue', $cell['state']);
        $this->assertFalse($cell['is_official']);
        $this->assertTrue($cell['is_rogue']);
        $this->assertEquals('discovered_rogue', $cell['source']);
        $this->assertGreaterThanOrEqual(1, $grid['stats']['rogue']);
    }

    public function test_ipam_grid_ajax_endpoint_returns_json_structure(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.ipam.grid', ['subnet' => '10.20.23.0/24']));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'subnet',
            'network_ip',
            'broadcast_ip',
            'site',
            'stats' => [
                'total',
                'occupied',
                'free',
                'reserved',
                'rogue',
                'percent_used',
                'usable_capacity',
            ],
            'cells' => [
                '*' => [
                    'ip',
                    'octet',
                    'state',
                    'badge',
                    'is_official',
                    'is_rogue',
                    'is_reserved',
                    'display_label',
                ],
            ],
        ]);
    }
}
