<?php

namespace Tests\Feature;

use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GlpiDeployTest extends TestCase
{
    use DatabaseTransactions;
    public function test_deploy_index_page_is_accessible(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        // Crear dispositivo descubierto de prueba
        $device = DiscoveredDevice::firstOrCreate(
            ['mac_address' => '00:11:22:33:44:55'],
            [
                'ip_address' => '192.168.1.199',
                'hostname' => 'PC-TEST-DEPLOY',
                'vendor' => 'Dell Inc.',
                'device_type' => 'workstation',
                'os_detected' => 'Windows 10 Pro (x86)',
                'classification_status' => 'pendiente',
            ]
        );

        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy'));
        $response->assertStatus(200);
        $response->assertSee('Despliegue Remoto de Agente GLPI');
        $response->assertSee('Sedes CIAU');
        $response->assertSee('Todas las Sedes & Fuentes', false);

        // Buscar específicamente el dispositivo de prueba vía escaneo de subred
        $searchResponse = $this->actingAs($admin)->get(route('admin.inventory.deploy', [
            'search' => '192.168.1.199',
            'scan_subnet' => '192.168.1.0/24',
        ]));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('PC-TEST-DEPLOY');
        $searchResponse->assertSee('192.168.1.199');
        $searchResponse->assertSee('Windows 10 Pro (x86)');
        $searchResponse->assertSee('Instalar');

        // Filtrar por sede CIAU CONSOLIDADO (Sede ID: 3)
        $siteResponse = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['site' => 3]));
        $siteResponse->assertStatus(200);
        $siteResponse->assertSee('192.168.107.154');
        $siteResponse->assertSee('JEFE OFICINA');
        $siteResponse->assertSee('CIAU CONSOLIDADO');

        // Limpiar
        $device->delete();
    }

    public function test_quick_status_endpoint(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin']);
        }

        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.status'), [
            'ip' => '192.168.1.999',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['reporting' => false]);
    }

    public function test_registered_non_pc_devices_are_excluded_and_pcs_are_included(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        // Consultar la vista buscando el Router Principal (192.168.1.1) -> NO debe tener botón de despliegue
        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['search' => '192.168.1.1']));
        $response->assertStatus(200);
        $response->assertDontSee("openDeployModal('192.168.1.1'", false);

        // Consultar la vista buscando un Switch (192.168.1.2) -> NO debe aparecer
        $responseSw = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['search' => '192.168.1.2']));
        $responseSw->assertStatus(200);
        $responseSw->assertDontSee('SW01 - Cisco Principal');

        // Consultar la vista buscando una Impresora registrada (192.168.106.253) -> NO debe aparecer
        $responseImp = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['search' => '192.168.106.253']));
        $responseImp->assertStatus(200);
        $responseImp->assertDontSee('IMPRESORA RED');

        // Consultar un equipo PC (192.168.106.240 - TAQUILLA 01) -> SÍ debe aparecer con botón de instalación
        $responsePc = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['search' => '192.168.106.240']));
        $responsePc->assertStatus(200);
        $responsePc->assertSee('TAQUILLA 01');
        $responsePc->assertSee('Sin Agente GLPI');
        $responsePc->assertSee("openDeployModal('192.168.106.240'", false);
    }

    public function test_header_compact_buttons_rendered(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy'));
        $response->assertStatus(200);
        $response->assertSee('Explorar Instaladores');
        $response->assertSee('Comandos 1-Clic');
        $response->assertSee('Escanear Segmento');
        $response->assertSee('folder_zip');
        $response->assertSee('terminal');
        $response->assertSee('radar');
    }

    public function test_default_view_shows_only_official_inventory_devices(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy'));
        $response->assertStatus(200);

        // No debe mostrar equipos desconocidos de la WAN central ajenos a las sedes
        $response->assertDontSee('Host-192-168-0-0');
        $response->assertDontSee('srv-aplicacion.cadafe.gov.ve');
        $response->assertDontSee('ldapr2-t4.empresa.com.ve');

        // Equipos no-PC como Router y Switch no deben aparecer con botón de despliegue
        $response->assertDontSee("openDeployModal('192.168.1.1'", false);
        $response->assertDontSee('SW01 - Cisco Principal');

        // Sí debe mostrar PCs oficiales de las sedes registradas (usando búsqueda para evitar límites de paginación)
        $responseSearch = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['search' => 'JEFE OFICINA']));
        $responseSearch->assertSee('JEFE OFICINA');
    }

    public function test_promote_discovered_device_to_printer_moves_it_out_of_deploy(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $ip = '192.168.1.188';
        DiscoveredDevice::create([
            'ip_address' => $ip,
            'mac_address' => '00:11:22:33:44:88',
            'hostname' => 'HP-OFFICEJET-TEST',
            'vendor' => 'HP Inc.',
            'device_type' => 'printer',
            'classification_status' => 'pendiente',
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.promote-asset'), [
            'ip' => $ip,
            'mac' => '00:11:22:33:44:88',
            'name' => 'IMPRESORA ARCHIVO TEST',
            'type' => 'printer',
            'monitored_site_id' => 1,
            'brand' => 'HP',
            'model' => 'OfficeJet',
            'notes' => 'Catalogado desde prueba de despliegue',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action' => 'moved',
            'ip' => $ip,
            'name' => 'IMPRESORA ARCHIVO TEST',
            'type' => 'printer',
        ]);

        // Verificar persistencia en MariaDB
        $this->assertDatabaseHas('inventory_assets', [
            'ip_address' => $ip,
            'name' => 'IMPRESORA ARCHIVO TEST',
            'type' => 'printer',
        ]);

        // Ahora en la vista de despliegue debe quedar excluida al ser un no-PC registrado
        $viewResp = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['search' => $ip]));
        $viewResp->assertStatus(200);
        $viewResp->assertDontSee('IMPRESORA ARCHIVO TEST');
    }

    public function test_promote_discovered_device_to_desktop_registers_it_and_retains_in_deploy(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $ip = '192.168.1.187';
        DiscoveredDevice::create([
            'ip_address' => $ip,
            'mac_address' => '00:11:22:33:44:87',
            'hostname' => 'PC-NUEVO-TEST',
            'vendor' => 'Dell Inc.',
            'device_type' => 'workstation',
            'classification_status' => 'pendiente',
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.promote-asset'), [
            'ip' => $ip,
            'mac' => '00:11:22:33:44:87',
            'name' => 'TAQUILLA ATENCION TEST',
            'type' => 'desktop',
            'monitored_site_id' => 1,
            'brand' => 'Dell',
            'model' => 'OptiPlex',
            'notes' => 'Catalogado como estación de trabajo',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action' => 'registered',
            'ip' => $ip,
            'name' => 'TAQUILLA ATENCION TEST',
            'type' => 'desktop',
        ]);

        // Verificar persistencia en MariaDB
        $this->assertDatabaseHas('inventory_assets', [
            'ip_address' => $ip,
            'name' => 'TAQUILLA ATENCION TEST',
            'type' => 'desktop',
        ]);

        // En la vista de despliegue debe aparecer con la etiqueta y botón de instalación
        $viewResp = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['search' => $ip]));
        $viewResp->assertStatus(200);
        $viewResp->assertSee('TAQUILLA ATENCION TEST');
        $viewResp->assertSee("openDeployModal('{$ip}'", false);
    }

    public function test_scan_subnet_filters_and_shows_banner(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['scan_subnet' => '192.168.106.0/24']));
        $response->assertStatus(200);
        $response->assertSee('Resultados de Escaneo en Subred:');
        $response->assertSee('192.168.106.0/24');
        $response->assertSee('Cerrar Escaneo (Ver Solo Inventario)');

        // Debe contener equipos de Morón
        $response->assertSee('192.168.106.240');
        // No debe contener equipos de Valle Seco (192.168.1.x)
        $response->assertDontSee('ROUTER WIFI SIGECOM');
        $response->assertDontSee('192.168.1.64');
    }

    public function test_scan_subnet_api_endpoint_validation(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        // Validación de subred inválida
        $respInvalid = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.scan-subnet'), [
            'subnet' => 'invalid-subnet',
        ]);
        $respInvalid->assertStatus(422);

        // Validación de subred demasiado amplia (/16)
        $respWide = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.scan-subnet'), [
            'subnet' => '192.168.0.0/16',
        ]);
        $respWide->assertStatus(422);
    }

    public function test_mac_deduplication_updates_ip_and_avoids_duplicate_assets_on_dhcp_change(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $mac = 'AA:BB:CC:DD:EE:11';
        $oldIp = '192.168.1.190';
        $newIp = '192.168.1.195';

        // Crear activo existente
        $asset = InventoryAsset::create([
            'name' => 'PC MIGRACION DHCP',
            'type' => 'desktop',
            'status' => 'in_stock',
            'ip_address' => $oldIp,
            'mac_address' => $mac,
            'monitored_site_id' => 1,
            'brand' => 'HP',
            'is_active' => true,
        ]);

        // Registrar hallazgo con la misma MAC pero nueva IP
        DiscoveredDevice::create([
            'ip_address' => $newIp,
            'mac_address' => $mac,
            'hostname' => 'PC-HP-NUEVA-IP',
            'vendor' => 'HP Inc.',
            'device_type' => 'workstation',
            'classification_status' => 'pendiente',
        ]);

        // Promover el nuevo hallazgo
        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.promote-asset'), [
            'ip' => $newIp,
            'mac' => $mac,
            'name' => 'PC MIGRACION DHCP',
            'type' => 'desktop',
            'monitored_site_id' => 1,
            'brand' => 'HP',
            'notes' => 'Actualizado por DHCP',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action' => 'registered',
            'ip' => $newIp,
        ]);

        // Verificar que NO se duplicó el activo en MariaDB
        $this->assertEquals(1, InventoryAsset::where('mac_address', $mac)->count());

        // Verificar que el activo existente actualizó su IP
        $asset->refresh();
        $this->assertEquals($newIp, $asset->ip_address);

        // Verificar log de auditoría
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ip_reassigned',
            'auditable_id' => $asset->id,
            'module' => 'inventory',
        ]);
    }

    public function test_prodesk_and_optiplex_models_are_directly_supported_for_glpi(): void
    {
        /** @var \App\Services\Deployment\AgentDeployService $deployService */
        $deployService = app(\App\Services\Deployment\AgentDeployService::class);

        $itemProdesk = [
            'hostname' => 'CENCARCOM012015',
            'vendor' => 'HP Inc. (ProDesk 400 G5 MT)',
            'raw_os' => 'Windows 10 Pro',
            'discovered_device_type' => 'printer', // Aunque un sondeo previo lo haya marcado erróneamente como impresora
        ];

        // Debe prevalecer como PC soportada para GLPI
        $isSupported = $deployService->isGlpiSupportedDevice($itemProdesk, null, 'windows');
        $this->assertTrue($isSupported, 'ProDesk debe prevalecer como soportado para GLPI');

        // Y la sugerencia debe ser estación de trabajo (PC Desktop)
        $suggestion = $deployService->suggestDeviceType($itemProdesk);
        $this->assertEquals('desktop', $suggestion['type']);
        $this->assertTrue($suggestion['is_workstation']);
    }

    public function test_bulk_promote_endpoint_processes_multiple_devices_with_mac_deduplication(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $mac1 = '11:22:33:44:55:AA';
        $mac2 = '11:22:33:44:55:BB';

        // Dispositivo 1: ya existía con otra IP
        $asset1 = InventoryAsset::create([
            'name' => 'ESTACION BULK 01',
            'type' => 'desktop',
            'status' => 'in_stock',
            'ip_address' => '192.168.1.211',
            'mac_address' => $mac1,
            'monitored_site_id' => 1,
            'is_active' => true,
        ]);

        $payload = [
            'devices' => [
                [
                    'ip' => '192.168.1.212', // Cambió de 211 a 212
                    'mac' => $mac1,
                    'hostname' => 'PC-REASSIGNED',
                    'vendor' => 'HP Inc.',
                ],
                [
                    'ip' => '192.168.1.213', // Nuevo
                    'mac' => $mac2,
                    'hostname' => 'PC-BRAND-NEW',
                    'vendor' => 'Dell Inc.',
                ]
            ],
            'type' => 'desktop',
            'monitored_site_id' => 1,
            'name_prefix' => 'PUESTO OPERATIVO',
            'notes' => 'Catalogación por lote test',
        ];

        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.bulk-promote'), $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 2,
            'is_workstation' => true,
        ]);

        // El activo 1 no se duplicó y actualizó su IP a 212
        $this->assertEquals(1, InventoryAsset::where('mac_address', $mac1)->count());
        $asset1->refresh();
        $this->assertEquals('192.168.1.212', $asset1->ip_address);

        // El activo 2 se creó
        $this->assertDatabaseHas('inventory_assets', [
            'mac_address' => $mac2,
            'ip_address' => '192.168.1.213',
            'type' => 'desktop',
        ]);
    }

    public function test_promote_ip_phone_and_access_point_sets_device_type_correctly_without_truncation(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $macPhone = '00:04:0D:AA:BB:CC';
        $ipPhone = '192.168.1.181';

        DiscoveredDevice::create([
            'ip_address' => $ipPhone,
            'mac_address' => $macPhone,
            'hostname' => 'AVAYA-TEST',
            'vendor' => 'Avaya Inc.',
            'device_type' => 'unknown',
            'classification_status' => 'pendiente',
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.promote-asset'), [
            'ip' => $ipPhone,
            'mac' => $macPhone,
            'name' => 'TELEFONO AVAYA PRUEBA',
            'type' => 'ip_phone',
            'monitored_site_id' => 1,
            'brand' => 'Avaya',
            'notes' => 'Teléfono de prueba',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action' => 'moved',
        ]);

        $this->assertDatabaseHas('discovered_devices', [
            'ip_address' => $ipPhone,
            'device_type' => 'phone',
            'classification_status' => 'clasificado',
            'is_authorized' => 1,
        ]);

        $this->assertDatabaseHas('inventory_assets', [
            'mac_address' => $macPhone,
            'ip_address' => $ipPhone,
            'type' => 'ip_phone',
        ]);
    }

    public function test_offline_devices_are_excluded_when_scanning_subnet(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $activeIp = '192.168.1.218';
        $offlineIp = '192.168.1.219';

        DiscoveredDevice::create([
            'ip_address' => $activeIp,
            'mac_address' => '00:11:22:33:44:88',
            'hostname' => 'PC-ACTIVE-TEST',
            'vendor' => 'HP Inc.',
            'device_type' => 'workstation',
            'classification_status' => 'pendiente',
            'is_active' => true,
        ]);

        DiscoveredDevice::create([
            'ip_address' => $offlineIp,
            'mac_address' => '00:11:22:33:44:99',
            'hostname' => 'PC-OFFLINE-TEST',
            'vendor' => 'HP Inc.',
            'device_type' => 'workstation',
            'classification_status' => 'pendiente',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['scan_subnet' => '192.168.1.0/24', 'search' => 'PC-ACTIVE-TEST']));
        $response->assertStatus(200);
        $response->assertSee($activeIp);

        $responseOffline = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['scan_subnet' => '192.168.1.0/24', 'search' => 'PC-OFFLINE-TEST']));
        $responseOffline->assertStatus(200);
        $responseOffline->assertDontSee($offlineIp);
    }

    public function test_os_detected_and_hostname_from_deep_fingerprint_are_displayed(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'terms_accepted' => true]);
        }

        $ip = '192.168.1.217';
        DiscoveredDevice::create([
            'ip_address' => $ip,
            'mac_address' => '00:11:22:33:44:77',
            'hostname' => 'CENCARDIS-0200',
            'vendor' => 'Hon Hai Precision Industry (Foxconn)',
            'os_detected' => 'Windows 7 Ultimate SP1',
            'open_ports' => '[135, 139, 445, 5800, 5900]',
            'device_type' => 'workstation',
            'is_active' => true,
            'classification_status' => 'pendiente',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy', ['scan_subnet' => '192.168.1.0/24', 'search' => 'CENCARDIS-0200']));
        $response->assertStatus(200);
        $response->assertSee('CENCARDIS-0200');
        $response->assertSee('Windows 7 Ultimate SP1');
    }
}

