<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminPeripheralManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected MonitoredSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first();
        $this->site = MonitoredSite::first() ?? MonitoredSite::create([
            'name' => 'Sede Prueba ITAM ' . uniqid(),
            'ip_range' => '192.168.1.0/24',
        ]);
    }

    public function test_peripherals_index_is_accessible_and_lists_kpis(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin.inventory.peripherals.index'));

        $response->assertStatus(200);
        $response->assertSee('Periféricos &amp; Stock de Hardware', false);
        $response->assertSee('Total Periféricos');
        $response->assertSee('Registrar Periférico');
        $response->assertSee('Dashboard');
        $response->assertSee('Centro de Operaciones de Red + Gestión de Activos de TI');
        $response->assertDontSee('Total Parque');
        $response->assertDontSee('Bóveda');
        $response->assertDontSee('Boveda');
        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
    }

    public function test_can_create_peripheral_in_stock(): void
    {
        $this->actingAs($this->adminUser);

        $serial = 'SN-KBD-' . rand(10000, 99999);

        $response = $this->post(route('admin.inventory.peripherals.store'), [
            'type' => 'keyboard',
            'brand' => 'Logitech',
            'model' => 'K120 USB',
            'serial_number' => $serial,
            'status' => 'in_stock',
            'monitored_site_id' => $this->site->id,
            'notes' => 'Teclado nuevo en caja para repuesto',
        ]);

        $response->assertRedirect(route('admin.inventory.peripherals.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_peripherals', [
            'type' => 'keyboard',
            'brand' => 'Logitech',
            'serial_number' => $serial,
            'status' => 'in_stock',
            'monitored_site_id' => $this->site->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'inventory',
            'auditable_type' => InventoryPeripheral::class,
            'event' => 'created',
        ]);
    }

    public function test_can_create_damaged_peripheral_with_reason(): void
    {
        $this->actingAs($this->adminUser);

        $serial = 'SN-MON-DAMAGED-' . rand(10000, 99999);

        $response = $this->post(route('admin.inventory.peripherals.store'), [
            'type' => 'monitor',
            'brand' => 'Samsung',
            'model' => 'SyncMaster 19',
            'serial_number' => $serial,
            'status' => 'damaged',
            'monitored_site_id' => $this->site->id,
            'notes' => 'Pantalla con líneas horizontales y falla en fuente interna. Para desincorporación.',
        ]);

        $response->assertRedirect(route('admin.inventory.peripherals.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_peripherals', [
            'type' => 'monitor',
            'serial_number' => $serial,
            'status' => 'damaged',
            'monitored_site_id' => $this->site->id,
        ]);
    }

    public function test_can_update_peripheral_status_to_damaged(): void
    {
        $this->actingAs($this->adminUser);

        $peripheral = InventoryPeripheral::create([
            'asset_tag' => 'PWR-' . rand(1000, 9999),
            'type' => 'surge_protector',
            'brand' => 'Forza',
            'model' => 'FVR-1200',
            'serial_number' => 'SN-PWR-' . rand(10000, 99999),
            'status' => 'in_stock',
            'monitored_site_id' => $this->site->id,
        ]);

        $response = $this->put(route('admin.inventory.peripherals.update', $peripheral->id), [
            'type' => 'surge_protector',
            'brand' => 'Forza',
            'model' => 'FVR-1200',
            'serial_number' => $peripheral->serial_number,
            'status' => 'damaged',
            'monitored_site_id' => $this->site->id,
            'notes' => 'Quemado por descarga eléctrica en toma principal.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $peripheral->refresh();
        $this->assertEquals('damaged', $peripheral->status);
        $this->assertStringContainsString('Quemado', $peripheral->notes);
    }

    public function test_can_unlink_peripheral_from_asset(): void
    {
        $this->actingAs($this->adminUser);

        $asset = InventoryAsset::create([
            'name' => 'PC-UNLINK-' . uniqid(),
            'asset_tag' => 'AST-UL-' . uniqid(),
            'type' => 'desktop',
            'status' => 'assigned',
            'monitored_site_id' => $this->site->id,
        ]);

        $peripheral = InventoryPeripheral::create([
            'asset_tag' => 'MSE-' . rand(1000, 9999),
            'type' => 'mouse',
            'brand' => 'Genius',
            'model' => 'DX-110',
            'serial_number' => 'SN-MSE-' . rand(10000, 99999),
            'status' => 'assigned',
            'current_asset_id' => $asset->id,
            'monitored_site_id' => $this->site->id,
        ]);

        $response = $this->post(route('admin.inventory.peripherals.unlink', $peripheral->id), [
            'status' => 'in_stock',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $peripheral->refresh();
        $this->assertNull($peripheral->current_asset_id);
        $this->assertEquals('in_stock', $peripheral->status);
    }

    public function test_can_delete_peripheral(): void
    {
        $this->actingAs($this->adminUser);

        $peripheral = InventoryPeripheral::create([
            'asset_tag' => 'DEL-' . rand(1000, 9999),
            'type' => 'other',
            'brand' => 'Genérico',
            'model' => 'Accesorio',
            'serial_number' => 'SN-DEL-' . rand(10000, 99999),
            'status' => 'in_stock',
            'monitored_site_id' => $this->site->id,
        ]);

        $response = $this->delete(route('admin.inventory.peripherals.destroy', $peripheral->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('inventory_peripherals', [
            'id' => $peripheral->id,
        ]);
    }

    public function test_devices_view_contains_peripherals_tab_and_no_boveda(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin.devices.index'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.inventory.peripherals.index'));
        $response->assertSee('Periféricos &amp; Stock', false);
        $response->assertDontSee('En Bóveda');
        $response->assertSee('En Stock (Disponible / Almacén)');
    }
}
