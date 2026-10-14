<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminNetworkDeviceLiveFilterTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected MonitoredSite $site;
    protected InventoryAsset $testAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->site = MonitoredSite::first() ?? MonitoredSite::create(['name' => 'Sede Prueba Test', 'ip' => '10.20.99.1', 'is_active' => true]);

        $this->testAsset = InventoryAsset::firstOrCreate(
            ['ip_address' => '10.20.23.199'],
            [
                'name' => 'TAQUILLA-TEST-01',
                'normal_state_msg' => 'TAQUILLA OPERATIVA 01',
                'asset_tag' => 'AST-LIVE-999',
                'serial_number' => 'SN-LIVE-FILTER-999',
                'type' => 'desktop',
                'status' => 'assigned',
                'custodian_name' => 'Operador Taquilla Test',
                'is_active' => true,
                'monitored_site_id' => $this->site->id,
            ]
        );
    }

    public function test_devices_index_renders_live_filter_elements_and_metadata_attributes(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.devices.index'));

        $response->assertStatus(200);

        // Verificar elementos del buscador y filtros reactivos en vivo
        $response->assertSee('id="device-filter-form"', false);
        $response->assertSee('id="device-search-input"', false);
        $response->assertSee('oninput="onSearchInput(event)"', false);
        $response->assertSee('id="device-site-select"', false);
        $response->assertSee('id="device-type-select"', false);
        $response->assertSee('id="device-status-select"', false);
        $response->assertSee('onchange="onFilterChange()"', false);

        // Verificar botón de limpieza y contenedor de tabla/kpi/paginación
        $response->assertSee('id="btn-clear-filters"', false);
        $response->assertSee('id="filter-idle-indicator"', false);
        $response->assertSee('id="devices-kpi-grid"', false);
        $response->assertSee('id="devices-table-body"', false);
        $response->assertSee('id="devices-pagination-wrapper"', false);

        // Verificar que las filas cuentan con los metadatos data-search, data-site-id, data-type, data-status
        $response->assertSee('class="hover:bg-cyan-500/[0.03] transition-colors group cursor-pointer device-row item-searchable"', false);
        $response->assertSee('data-search=', false);
        $response->assertSee('data-site-id=', false);
        $response->assertSee('data-type=', false);
        $response->assertSee('data-status=', false);

        // Verificar que NO existe el botón manual previo de submit ("Filtrar")
        $response->assertDontSee('title="Aplicar Filtros"');
    }

    public function test_devices_live_filter_by_search_query_ajax_returns_matching_devices(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.devices.index', ['search' => 'TAQUILLA OPERATIVA 01']));

        $response->assertStatus(200);
        $response->assertSee('TAQUILLA OPERATIVA 01');
        $response->assertSee('10.20.23.199');
    }

    public function test_devices_live_filter_by_site_and_type_and_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.devices.index', [
                'site_id' => $this->site->id,
                'type' => 'desktop',
                'status' => 'active',
            ]));

        $response->assertStatus(200);
        $response->assertSee('id="devices-table-body"', false);
        $response->assertSee('id="devices-kpi-grid"', false);
        $response->assertSee($this->testAsset->ip);
    }
}
