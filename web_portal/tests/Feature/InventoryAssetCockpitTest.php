<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\MaintenanceTicket;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryAssetCockpitTest extends TestCase
{
    use DatabaseTransactions;
    protected User $adminUser;
    protected MonitoredSite $site;
    protected InventoryAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first();
        $this->site = MonitoredSite::first();

        $this->asset = InventoryAsset::firstOrCreate(
            ['serial_number' => 'COCKPIT-SN-12345'],
            [
                'asset_tag' => 'AST-COCKPIT-01',
                'name' => 'PC-COCKPIT-TEST',
                'type' => 'desktop',
                'brand' => 'Dell',
                'model' => 'OptiPlex 7050',
                'status' => 'assigned',
                'custodian_name' => 'Custodio Test ITAM',
                'monitored_site_id' => $this->site->id,
            ]
        );
    }

    public function test_assets_catalog_displays_unified_itam_tabs(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assets.index'));

        $response->assertStatus(200);
        $response->assertSee('Catálogo de Activos');
        $response->assertSee('Asignaciones &amp; Actas', false);
        $response->assertSee('Mantenimiento TI');
        $response->assertSee('Despliegue Remoto GLPI');
        $response->assertSee('Garantías &amp; Ciclo', false);
        $response->assertSee('Energía &amp; UPS', false);
    }

    public function test_assets_table_integrates_actas_and_maintenance_columns(): void
    {
        $assignment = InventoryAssignment::firstOrCreate(
            ['assignment_code' => 'UINF-4.05-COCKPIT-001'],
            [
                'inventory_asset_id' => $this->asset->id,
                'user_id' => $this->adminUser->id,
                'custodian_name' => 'Ing. María Gómez',
                'movement_type' => 'assignment',
                'provenance' => 'nuevo',
                'assigned_at' => now(),
                'status' => 'active',
            ]
        );

        $ticket = MaintenanceTicket::firstOrCreate(
            ['ticket_code' => 'MNT-COCKPIT-001'],
            [
                'inventory_asset_id' => $this->asset->id,
                'technician_user_id' => $this->adminUser->id,
                'type' => 'preventive',
                'status' => 'scheduled',
                'priority' => 'medium',
                'title' => 'Mantenimiento Preventivo Trimestral',
                'description' => 'Limpieza de ventiladores y actualización de parches',
            ]
        );

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assets.index'));

        $response->assertStatus(200);

        // Cabeceras de tabla integradas (7 columnas oficiales)
        $response->assertSee('Tiene Asignación');
        $response->assertSee('Mantenimiento');

        // Datos del activo
        $response->assertSee('PC-COCKPIT-TEST');
        $response->assertSee('Ing. María Gómez');
        $response->assertSee('UINF-4.05-COCKPIT-001');

        // Presencia del modal y su mapa de datos
        $response->assertSee('id="maintenanceModal"', false);
        $response->assertSee('MNT-COCKPIT-001');
        $response->assertSee('Mantenimiento Preventivo Trimestral');
    }

    public function test_accordion_renders_pc_specs_and_four_peripherals(): void
    {
        // Crear periféricos para el activo PC
        \App\Models\InventoryPeripheral::create([
            'asset_tag' => 'MON-TEST-99',
            'type' => 'monitor',
            'brand' => 'Samsung',
            'model' => 'SyncMaster 24',
            'serial_number' => 'SAM-MON-7788',
            'status' => 'assigned',
            'current_asset_id' => $this->asset->id,
            'monitored_site_id' => $this->site->id,
        ]);

        \App\Models\InventoryPeripheral::create([
            'asset_tag' => 'KBD-TEST-99',
            'type' => 'keyboard',
            'brand' => 'Logitech',
            'model' => 'K120 USB',
            'serial_number' => 'LOGI-KBD-1122',
            'status' => 'assigned',
            'current_asset_id' => $this->asset->id,
            'monitored_site_id' => $this->site->id,
        ]);

        // Registrar un cambio en el historial
        \App\Models\InventoryComponentHistory::create([
            'inventory_asset_id' => $this->asset->id,
            'component_type' => 'monitor',
            'action' => 'replaced',
            'old_value' => 'LG 19"',
            'new_value' => 'Samsung SyncMaster 24"',
            'details' => 'Reemplazo por solicitud de usuario',
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assets.index'));

        $response->assertStatus(200);

        // Periféricos del puesto de trabajo
        $response->assertSee('Periféricos de Puesto de Trabajo');
        $response->assertSee('Samsung SyncMaster 24');
        $response->assertSee('SAM-MON-7788');
        $response->assertSee('Logitech');

        // Histórico de cambios
        $response->assertSee('Histórico de Modificaciones y Componentes');
        $response->assertSee('LG 19"');
        $response->assertSee('Samsung SyncMaster 24"');
    }

    public function test_store_asset_with_workspace_peripherals(): void
    {
        $payload = [
            'name' => 'PC-ADMIN-OFICINA',
            'asset_tag' => 'AST-ADM-0088',
            'type' => 'desktop',
            'status' => 'assigned',
            'brand' => 'HP',
            'model' => 'ProDesk 400 G6',
            'serial_number' => 'HP-PD-998877',
            'monitored_site_id' => $this->site->id,
            'custodian_name' => 'Carlos Mendoza',
            // Periféricos
            'monitor_brand' => 'Dell 24 pulgadas',
            'monitor_serial' => 'DELL-MON-4455',
            'keyboard_brand' => 'Genius',
            'keyboard_serial' => 'GEN-KB-001',
            'mouse_brand' => 'Genius',
            'mouse_serial' => 'GEN-MS-002',
            'surge_protector_brand' => 'Forza 1200VA',
            'surge_protector_serial' => 'FZ-PWR-998',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assets.store'), $payload);

        $response->assertRedirect(route('admin.inventory.assets.index'));

        $asset = InventoryAsset::where('serial_number', 'HP-PD-998877')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('PC-ADMIN-OFICINA', $asset->name);

        // Verificar los 4 periféricos creados y asociados
        $peripherals = \App\Models\InventoryPeripheral::where('current_asset_id', $asset->id)->get();
        $this->assertCount(4, $peripherals);
        $this->assertTrue($peripherals->contains('type', 'monitor'));
        $this->assertTrue($peripherals->contains('type', 'keyboard'));
        $this->assertTrue($peripherals->contains('type', 'mouse'));
        $this->assertTrue($peripherals->contains('type', 'surge_protector'));
    }

    public function test_bulk_maintenance_scheduling(): void
    {
        $asset2 = InventoryAsset::create([
            'name' => 'SW-CORE-TEST',
            'type' => 'switch',
            'status' => 'assigned',
            'serial_number' => 'SW-TEST-SN-5566',
            'monitored_site_id' => $this->site->id,
        ]);

        $payload = [
            'selected_asset_ids' => "{$this->asset->id},{$asset2->id}",
            'type' => 'preventive',
            'priority' => 'high',
            'scheduled_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'technician_user_id' => $this->adminUser->id,
            'notes' => 'Mantenimiento preventivo general de sede',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assets.bulk-maintenance'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $tickets = MaintenanceTicket::whereIn('inventory_asset_id', [$this->asset->id, $asset2->id])->get();
        $this->assertCount(2, $tickets);
        $this->assertEquals('preventive', $tickets->first()->type);
        $this->assertEquals($this->adminUser->id, $tickets->first()->technician_user_id);
    }

    public function test_table_renders_icon_only_buttons_with_tooltips_and_physical_scan_modal(): void
    {
        $unassignedAsset = InventoryAsset::create([
            'name' => 'PC-LIBRE-TEST',
            'type' => 'desktop',
            'status' => 'in_stock',
            'serial_number' => 'SN-LIBRE-999',
            'monitored_site_id' => $this->site->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assets.index'));

        $response->assertStatus(200);

        // 1. Tooltips de estado sin asignar con icono interactivo
        $response->assertSee('title="Estado: Sin Asignar"', false);
        $response->assertSee('title="Sin Asignar: Cargar Hoja Física de Asignación Escaneada"', false);
        $response->assertSee('title="Sin Asignar: Emitir Nueva Acta UINF-4.05 en Sistema"', false);

        // 2. Columna mantenimiento con botón e icono de planificación
        $response->assertSee('title="Planificar Mantenimiento Preventivo o Correctivo"', false);

        // 3. Presencia del modal para digitalizar documento físico
        $response->assertSee('id="uploadPhysicalScanModal"', false);
        $response->assertSee('Subir Hoja Física de Asignación');
        $response->assertSee('name="scan_file"', false);

        // 4. Presencia de la tarjeta de asignación en el acordeón
        $response->assertSee('Activo sin Asignación ni Documento Registrado', false);
        $response->assertSee('Subir Hoja Física Existente', false);
    }

    public function test_adding_device_from_admin_sites_creates_inventory_asset(): void
    {
        $uniqueIp = '10.20.98.55';
        $site = MonitoredSite::first();

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.sites.devices.store', $site->id), [
                'name' => 'PC-NUEVO-SITE-TEST',
                'ip' => $uniqueIp,
                'mac' => '00:11:22:33:44:99',
                'vendor_data' => 'HP Test',
                'model' => 'ProDesk 600',
                'serial' => 'SN-TEST-SITE-99',
                'access_type' => 'SSH',
                'access_port' => 22,
                'is_active' => 1,
                'sync_inventory' => 1,
                'inventory_action' => 'create',
                'asset_type' => 'desktop',
                'asset_status' => 'assigned',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('monitored_site_devices', [
            'ip' => $uniqueIp,
            'name' => 'PC-NUEVO-SITE-TEST',
        ]);

        $this->assertDatabaseHas('inventory_assets', [
            'ip_address' => $uniqueIp,
            'name' => 'PC-NUEVO-SITE-TEST',
            'type' => 'desktop',
            'model' => 'ProDesk 600',
            'brand' => 'HP Test',
            'monitored_site_id' => $site->id,
        ]);
    }

    public function test_adding_device_from_admin_sites_can_link_existing_inventory_asset(): void
    {
        $site = MonitoredSite::first();
        $uniqueIp = '10.20.98.56';

        $existingAsset = InventoryAsset::create([
            'asset_tag' => 'NET-TEST-LINK-01',
            'name' => 'SW-TEST-EXISTING',
            'type' => 'switch',
            'status' => 'assigned',
            'serial_number' => 'SN-LINK-9988',
            'monitored_site_id' => $site->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.sites.devices.store', $site->id), [
                'name' => 'SW-SITIO-01',
                'ip' => $uniqueIp,
                'mac' => '00:11:22:33:44:88',
                'vendor_data' => 'Cisco Systems',
                'model' => 'Catalyst 2960',
                'serial' => 'SN-LINK-9988',
                'access_type' => 'TELNET',
                'access_port' => 23,
                'is_active' => 1,
                'sync_inventory' => 1,
                'inventory_action' => 'link',
                'existing_asset_id' => $existingAsset->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $existingAsset->refresh();
        $this->assertEquals($uniqueIp, $existingAsset->ip_address);
        $this->assertNotNull($existingAsset->monitored_network_device_id);
    }

    public function test_deleting_device_from_admin_sites_unlinks_inventory_asset_without_deleting_it(): void
    {
        $site = MonitoredSite::first();
        $uniqueIp = '10.20.98.57';

        $siteDevice = $site->devices()->create([
            'device_number' => 99,
            'name' => 'DEVICE-TO-DELETE',
            'ip' => $uniqueIp,
            'is_active' => true,
        ]);

        $netDevice = \App\Models\MonitoredNetworkDevice::create([
            'monitored_site_id' => $site->id,
            'device_number' => 99,
            'name' => 'DEVICE-TO-DELETE',
            'ip' => $uniqueIp,
            'is_active' => true,
        ]);

        $asset = InventoryAsset::create([
            'asset_tag' => 'NET-DEL-TEST',
            'name' => 'ASSET-TO-PRESERVE',
            'type' => 'desktop',
            'status' => 'assigned',
            'ip_address' => $uniqueIp,
            'serial_number' => 'SN-DEL-TEST',
            'monitored_site_id' => $site->id,
            'monitored_network_device_id' => $netDevice->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.sites.devices.destroy', $siteDevice->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('monitored_site_devices', [
            'id' => $siteDevice->id,
        ]);

        $this->assertDatabaseHas('inventory_assets', [
            'id' => $asset->id,
            'monitored_network_device_id' => null,
        ]);
    }
}


