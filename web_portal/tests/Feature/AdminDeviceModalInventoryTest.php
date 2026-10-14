<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDeviceModalInventoryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Valida que el modal de creación de dispositivos renderice los nuevos controles,
     * categorías agrupadas, botón de nueva sede, selector de estado operativo,
     * y garantice la erradicación total de las siglas SSOT o NOC SSOT.
     */
    public function test_admin_devices_create_modal_renders_properly_without_ssot(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user, 'Debe existir un usuario administrador legítimo');

        $response = $this->actingAs($user)->get(route('admin.devices.index'));

        $response->assertStatus(200);

        // Certificar erradicación de siglas técnicas no deseadas
        $response->assertDontSee('NOC SSOT', false);
        $response->assertDontSee('Identidad SSOT Sincronizada', false);

        // Certificar presencia de las nuevas funcionalidades
        $response->assertSee('modal-quick-site', false);
        $response->assertSee('openQuickSiteModal', false);
        $response->assertSee('+ Nueva Sede', false);
        $response->assertSee('create-status', false);
        $response->assertSee('Condición / Estado Operativo Inicial', false);
        $response->assertSee('create-glpi-wrapper', false);
        $response->assertSee('Despliegue Remoto de Agente GLPI', false);
        $response->assertSee('create-passive-notice', false);
        $response->assertSee('handleCreateDeviceTypeChange', false);
    }

    /**
     * Valida que la creación rápida de sedes vía AJAX (JSON) retorne la estructura esperada
     * para inyección dinámica en el modal sin recargar página.
     */
    public function test_quick_site_creation_returns_json_response(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->postJson(route('admin.sites.store'), [
            'name' => 'SEDE AUTOMATIZADA TEST ' . uniqid(),
            'ip' => '10.20.99.1',
            'phone_1' => '0242-1234567',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'site' => ['id', 'name', 'ip', 'letter'],
        ]);
    }

    /**
     * Valida el registro de un periférico pasivo sin IP (ej: Monitor / Protector)
     * confirmando que el backend asigne estatus inicial, deshabilite ping ICMP
     * y lo replique en inventory_peripherals para actas UINF-4.05.
     */
    public function test_create_passive_peripheral_without_ip_and_custom_status(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $site = MonitoredSite::first();
        $this->assertNotNull($site, 'Debe existir una sede registrada');

        $uniqueName = 'MONITOR OFICINA ' . uniqid();
        $response = $this->actingAs($user)->post(route('admin.devices.store'), [
            'monitored_site_id' => $site->id,
            'name' => $uniqueName,
            'type' => 'monitor',
            'status' => 'in_stock',
            'ip' => null,
            'vendor_data' => 'Samsung',
            'model' => 'SyncMaster 22',
            'serial' => 'SAM-MON-' . uniqid(),
            'access_type' => 'SIN SOPORTE',
        ]);

        $response->assertRedirect(route('admin.devices.index'));

        // Verificar que existe en inventory_assets
        $asset = InventoryAsset::where('name', $uniqueName)->first();
        $this->assertNotNull($asset, 'El activo debe estar registrado en inventory_assets');
        $this->assertEquals('monitor', $asset->type);
        $this->assertEquals('in_stock', $asset->status);
        $this->assertFalse((bool)$asset->is_active, 'Periférico pasivo sin IP no debe tener ICMP activo');

        // Verificar réplica en inventory_peripherals
        $per = InventoryPeripheral::where('serial_number', $asset->serial_number)->first();
        $this->assertNotNull($per, 'El periférico debe reflejarse en inventory_peripherals');
        $this->assertEquals('in_stock', $per->status);
        $this->assertEquals('monitor', $per->type);
    }

    /**
     * Valida que la columna GLPI en /admin/devices muestre 'N/A' para equipos de red
     * (routers, switches, firewalls, etc.) y reserve el botón de instalación GLPI
     * exclusivamente para estaciones de trabajo (desktop, laptop, server).
     */
    public function test_glpi_column_displays_na_for_network_infrastructure_and_glpi_actions_for_pcs(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get(route('admin.devices.index'));
        $response->assertStatus(200);

        // Confirmar que existe el indicador N/A para equipos no elegibles para agente GLPI
        $response->assertSee('No aplica agente GLPI para este tipo de dispositivo', false);
        $response->assertSee('N/A', false);

        // Verificar que un router o switch legítimo no tenga botón de instalar GLPI hacia su IP
        $router = InventoryAsset::where('type', 'router')->whereNotNull('ip_address')->first();
        if ($router) {
            $deployLinkForRouter = route('admin.inventory.deploy', ['search' => $router->ip]);
            $response->assertDontSee($deployLinkForRouter, false);
        }

        $switch = InventoryAsset::where('type', 'switch')->whereNotNull('ip_address')->first();
        if ($switch) {
            $deployLinkForSwitch = route('admin.inventory.deploy', ['search' => $switch->ip]);
            $response->assertDontSee($deployLinkForSwitch, false);
        }
    }

    /**
     * Valida que en /admin/devices se erradique la palabra 'custodio',
     * se muestren las métricas reales de 'Con Asignación' y 'Sin Asignación',
     * y los filtros 'assigned' y 'unassigned' operen dinámicamente con MariaDB.
     */
    public function test_assignment_metrics_and_unassigned_filter_without_custodio_word(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get(route('admin.devices.index'));
        $response->assertStatus(200);

        // Erradicación de la palabra custodio
        $response->assertDontSee('custodio', false);
        $response->assertDontSee('Custodio', false);
        $response->assertDontSee('CUSTODIO', false);

        // Presencia de las nuevas métricas y terminología oficial
        $response->assertSee('Con Asignación', false);
        $response->assertSee('Sin Asignación', false);
        $response->assertSee('Acta de Asignación', false);
        $response->assertSee('Pendiente de acta', false);

        // Probar filtro de equipos con asignación (debe responder 200 y reflejar la vista vacía si no hay actas)
        $respAssigned = $this->actingAs($user)->get(route('admin.devices.index', ['status' => 'assigned']));
        $respAssigned->assertStatus(200);

        // Probar filtro de equipos sin asignación (debe responder 200 y listar los equipos pendientes de acta)
        $respUnassigned = $this->actingAs($user)->get(route('admin.devices.index', ['status' => 'unassigned']));
        $respUnassigned->assertStatus(200);
        $respUnassigned->assertSee('Sin Asignación', false);

        // Verificar que se eliminó el botón redundante 'Ver Ficha Técnica Completa'
        $response->assertDontSee('Ver Ficha Técnica Completa', false);

        // Verificar que el tooltip de Tipo se encuentre presente
        $response->assertSee('Tipo de Dispositivo:', false);

        // Verificar que los badges de Acceso Remoto no muestran el puerto en el texto
        $response->assertDontSee('<span>TEL:23</span>', false);
        $response->assertDontSee('<span>WEB:80</span>', false);
        $response->assertDontSee('<span>SSH:22</span>', false);
        $response->assertDontSee('<span>VNC:5900</span>', false);
    }

    /**
     * Valida que la vista /admin/devices incluya el modal de asignación rápida LDAP,
     * los disparadores de apertura y los campos para formalizar el acta UINF-4.05.
     */
    public function test_quick_assign_modal_and_ldap_triggers_present_in_view(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get(route('admin.devices.index'));
        $response->assertStatus(200);

        // Modal express y triggers
        $response->assertSee('modal-quick-assign', false);
        $response->assertSee('openQuickAssignModal', false);
        $response->assertSee('quick-assign-ldap-search', false);
        $response->assertSee('quick-assign-custodian-name', false);
        $response->assertSee('quick-assign-custodian-id', false);
        $response->assertSee('quick-assign-custodian-dept', false);
        $response->assertSee('quick-assign-custodian-role', false);
        $response->assertSee('Formalizar y Generar Acta UINF-4.05', false);
        $response->assertSee('Devolver a Stock', false);

        // Bloque de asignación en creación
        $response->assertSee('create-assignment-wrapper', false);
        $response->assertSee('create-ldap-search', false);
        $response->assertSee('create-custodian-name', false);

        // Bloque de asignación en edición
        $response->assertSee('edit-assignment-action', false);
        $response->assertSee('edit-ldap-search', false);
        $response->assertSee('edit-custodian-name', false);
    }

    /**
     * Valida que el endpoint de búsqueda LDAP retorne estructura JSON enriquecida
     * con cédula, departamento y rol institucional.
     */
    public function test_ldap_user_search_endpoint_returns_json_with_attributes(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        // Buscar con término existente o que active el fallback local
        $response = $this->actingAs($user)->getJson(route('admin.users.ldap.search', ['q' => substr($user->name, 0, 3)]));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'results' => [
                '*' => ['uid', 'name', 'email', 'cedula', 'department', 'role']
            ]
        ]);
    }

    /**
     * Valida el registro express de asignación vía AJAX en InventoryAssignmentController
     * generando correlativo oficial UINF-4.05 y retornando urls de PDF y ficha.
     */
    public function test_ajax_assignment_store_creates_official_acta_and_updates_asset(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        // Crear activo de prueba en MariaDB
        $asset = InventoryAsset::create([
            'asset_tag' => 'ACT-TEST-' . uniqid(),
            'name' => 'PC DESPACHO ' . uniqid(),
            'type' => 'desktop',
            'status' => 'in_stock',
            'monitored_site_id' => $site->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.inventory.assignments.store'), [
            'inventory_asset_id' => $asset->id,
            'movement_type' => 'assignment',
            'custodian_name' => 'JUAN PEREZ GONZALEZ',
            'custodian_id_number' => 'V-15.482.314',
            'custodian_department' => 'DISTRIBUCION Y COMERCIALIZACION',
            'custodian_role' => 'ESPECIALISTA TECNICO',
            'observations' => 'Asignación express desde tabla de monitoreo',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'assignment' => ['id', 'assignment_code', 'custodian_name'],
            'code',
            'pdf_url',
            'show_url',
        ]);

        // Verificar que el activo pasó a 'assigned'
        $asset->refresh();
        $this->assertEquals('assigned', $asset->status);
        $this->assertEquals('JUAN PEREZ GONZALEZ', $asset->custodian_name);
        $this->assertEquals('V-15.482.314', $asset->custodian_id_number);
    }

    /**
     * Valida que al editar un dispositivo con acción 'release', se devuelva a stock,
     * se limpien los campos del responsable y se genere el acta ACTA-DEV.
     */
    public function test_edit_device_release_to_stock_generates_dev_acta(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        // Crear dispositivo y activo asignado
        $name = 'PC REASIGNAR ' . uniqid();
        $ip = '10.20.23.' . rand(150, 240);

        $responseCreate = $this->actingAs($user)->post(route('admin.devices.store'), [
            'monitored_site_id' => $site->id,
            'name' => $name,
            'type' => 'desktop',
            'ip' => $ip,
            'status' => 'assigned',
            'custodian_name' => 'PEDRO PICAPIEDRA',
            'custodian_id_number' => 'V-11.222.333',
            'custodian_department' => 'MANTENIMIENTO',
            'custodian_role' => 'SUPERVISOR',
            'access_type' => 'SSH',
        ]);

        $responseCreate->assertRedirect(route('admin.devices.index'));

        $device = \App\Models\MonitoredNetworkDevice::where('name', $name)->first();
        $this->assertNotNull($device);

        $asset = InventoryAsset::find($device->id);
        $this->assertNotNull($asset);
        $this->assertEquals('assigned', $asset->status);
        $this->assertEquals('PEDRO PICAPIEDRA', $asset->custodian_name);

        // Ahora editar el dispositivo liberándolo a stock
        $responseEdit = $this->actingAs($user)->put(route('admin.devices.update', $device->id), [
            'monitored_site_id' => $site->id,
            'name' => $name,
            'type' => 'desktop',
            'ip' => $ip,
            'access_type' => 'SSH',
            'assignment_action' => 'release',
        ]);

        $responseEdit->assertRedirect(route('admin.devices.index'));

        // Verificar que el activo volvió a in_stock y se limpió el custodio
        $asset->refresh();
        $this->assertEquals('in_stock', $asset->status);
        $this->assertNull($asset->custodian_name);
        $this->assertNull($asset->custodian_id_number);
    }

    public function test_admin_device_edit_with_serial_change_records_component_history_and_glpi_warning(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);
        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        $ip = '10.20.90.' . rand(100, 199);
        $device = \App\Models\MonitoredNetworkDevice::create([
            'monitored_site_id' => $site->id,
            'name' => 'PC TEST AUDIT SERIAL ' . uniqid(),
            'ip' => $ip,
            'serial' => 'SN-INIT-001',
            'access_type' => 'SSH',
            'is_active' => true,
        ]);

        $asset = InventoryAsset::findOrFail($device->id);
        $asset->update([
            'type' => 'desktop',
            'status' => 'in_stock',
            'last_glpi_sync_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('admin.devices.update', $device->id), [
            'monitored_site_id' => $site->id,
            'name' => $device->name,
            'type' => 'desktop',
            'ip' => $ip,
            'serial' => 'SN-MODIFIED-999',
            'access_type' => 'SSH',
        ]);

        $response->assertRedirect(route('admin.devices.index'));

        $device->refresh();
        $asset->refresh();
        $this->assertEquals('SN-MODIFIED-999', $device->serial);
        $this->assertEquals('SN-MODIFIED-999', $asset->serial_number);

        $history = \App\Models\InventoryComponentHistory::where('inventory_asset_id', $device->id)
            ->where('component_type', 'serial')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('modified', $history->action);
        $this->assertEquals('SN-INIT-001', $history->old_value['serial'] ?? null);
        $this->assertEquals('SN-MODIFIED-999', $history->new_value['serial'] ?? null);
        $this->assertStringContainsString('GLPI', $history->reason);
    }

    public function test_admin_device_edit_manages_station_peripherals_in_single_step(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);
        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        $ip = '10.20.90.' . rand(200, 250);
        $device = \App\Models\MonitoredNetworkDevice::create([
            'monitored_site_id' => $site->id,
            'name' => 'PC PUESTO PERIFERICOS ' . uniqid(),
            'ip' => $ip,
            'serial' => 'SN-PC-777',
            'access_type' => 'SSH',
            'is_active' => true,
        ]);

        $asset = InventoryAsset::findOrFail($device->id);
        $asset->update([
            'type' => 'desktop',
            'status' => 'in_stock',
        ]);

        // 1. Asociar los 4 periféricos de un solo golpe desde el modal
        $response = $this->actingAs($user)->put(route('admin.devices.update', $device->id), [
            'monitored_site_id' => $site->id,
            'name' => 'PC PUESTO PERIFERICOS',
            'type' => 'desktop',
            'ip' => $ip,
            'access_type' => 'SSH',
            'has_peripherals_form' => 1,
            'monitor_brand' => 'Samsung',
            'monitor_model' => 'SyncMaster',
            'monitor_serial' => 'SAM-123',
            'keyboard_brand' => 'Genius',
            'keyboard_model' => 'KB-110',
            'keyboard_serial' => 'GEN-456',
            'mouse_brand' => 'Logitech',
            'mouse_model' => 'M100',
            'mouse_serial' => 'LOG-789',
            'protector_type' => 'ups',
            'protector_brand' => 'APC',
            'protector_model' => 'Smart-UPS 1000',
            'protector_serial' => 'APC-001',
        ]);

        $response->assertRedirect(route('admin.devices.index'));

        $this->assertEquals(4, \App\Models\InventoryPeripheral::where('current_asset_id', $asset->id)->count());

        $monitor = \App\Models\InventoryPeripheral::where('current_asset_id', $asset->id)->where('type', 'monitor')->first();
        $this->assertNotNull($monitor);
        $this->assertEquals('Samsung', $monitor->brand);
        $this->assertEquals('SAM-123', $monitor->serial_number);

        // 2. Desvincular el monitor usando la casilla remove_monitor
        $responseRemove = $this->actingAs($user)->put(route('admin.devices.update', $device->id), [
            'monitored_site_id' => $site->id,
            'name' => 'PC PUESTO PERIFERICOS',
            'type' => 'desktop',
            'ip' => $ip,
            'access_type' => 'SSH',
            'has_peripherals_form' => 1,
            'remove_monitor' => 1,
            'keyboard_brand' => 'Genius',
            'keyboard_model' => 'KB-110',
            'keyboard_serial' => 'GEN-456',
            'mouse_brand' => 'Logitech',
            'mouse_model' => 'M100',
            'mouse_serial' => 'LOG-789',
            'protector_type' => 'ups',
            'protector_brand' => 'APC',
            'protector_model' => 'Smart-UPS 1000',
            'protector_serial' => 'APC-001',
        ]);

        $responseRemove->assertRedirect(route('admin.devices.index'));

        // El monitor quedó desvinculado (en stock) y quedan 3 vinculados al PC
        $monitor->refresh();
        $this->assertNull($monitor->current_asset_id);
        $this->assertEquals('in_stock', $monitor->status);
        $this->assertEquals(3, \App\Models\InventoryPeripheral::where('current_asset_id', $asset->id)->count());

        // Verificar histórico inmutable de remoción
        $histRemove = \App\Models\InventoryComponentHistory::where('inventory_asset_id', $asset->id)
            ->where('component_type', 'monitor')
            ->where('action', 'removed')
            ->first();
        $this->assertNotNull($histRemove);
    }

    /**
     * Valida la creación y edición de dispositivos con periféricos adicionales dinámicos
     * (impresoras, escáneres, etc.) y la presencia de ambas opciones comparativas en Blade.
     */
    public function test_device_creation_and_edition_with_extra_peripherals_dynamic_support(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        // 1. Verificar renderizado en Blade de la Arquitectura Unificada: Vista Continua en Bloques Modulares
        $responseView = $this->actingAs($user)->get(route('admin.devices.index'));
        $responseView->assertStatus(200);

        // Modal de Creación en Bloques Continuos
        $responseView->assertSee('modal-device-create', false);
        $responseView->assertSee('create-section-peripherals', false);
        $responseView->assertSee('create-extra-peripherals-list', false);
        $responseView->assertSee('addCreateExtraPeripheral', false);

        // Modal de Edición en Bloques Continuos (Unificado a Vista Continua Modular)
        $responseView->assertSee('modal-device-edit', false);
        $responseView->assertSee('edit-section-peripherals', false);
        $responseView->assertSee('edit-extra-peripherals-list', false);
        $responseView->assertSee('addEditExtraPeripheral', false);
        $responseView->assertDontSee('Vista Continua Modular', false);
        $responseView->assertDontSee('360°', false);

        // 2. Crear dispositivo con periférico extra (Impresora)
        $uniqueName = 'ESTACION PRUEBA EXTRAS ' . uniqid();
        $uniqueIp = '10.20.98.' . rand(10, 240);

        $responseCreate = $this->actingAs($user)->post(route('admin.devices.store'), [
            'monitored_site_id' => $site->id,
            'name' => $uniqueName,
            'type' => 'desktop',
            'ip' => $uniqueIp,
            'access_type' => 'SSH',
            'has_peripherals_form' => 1,
            'monitor_brand' => 'LG',
            'monitor_model' => '24MP400',
            'monitor_serial' => 'LG-' . uniqid(),
            'extra_peripherals' => [
                [
                    'type' => 'printer',
                    'brand' => 'HP',
                    'model' => 'LaserJet Pro M404n',
                    'serial' => 'HP-PRN-' . uniqid(),
                ],
            ],
        ]);

        $responseCreate->assertRedirect(route('admin.devices.index'));

        $asset = InventoryAsset::where('name', $uniqueName)->first();
        $this->assertNotNull($asset);

        // Verificar que se crearon el monitor y la impresora
        $printer = InventoryPeripheral::where('current_asset_id', $asset->id)
            ->where('type', 'printer')
            ->first();
        $this->assertNotNull($printer, 'La impresora adicional debe estar asociada al activo');
        $this->assertEquals('HP', $printer->brand);
        $this->assertEquals('LaserJet Pro M404n', $printer->model);

        // 3. Editar dispositivo: desvincular la impresora y agregar un escáner
        $responseEdit = $this->actingAs($user)->put(route('admin.devices.update', $asset->id), [
            'monitored_site_id' => $site->id,
            'name' => $uniqueName,
            'type' => 'desktop',
            'ip' => $uniqueIp,
            'access_type' => 'SSH',
            'has_peripherals_form' => 1,
            'extra_peripherals' => [
                [
                    'id' => $printer->id,
                    'type' => 'printer',
                    'remove' => 1,
                ],
                [
                    'type' => 'scanner',
                    'brand' => 'Fujitsu',
                    'model' => 'ScanSnap iX1600',
                    'serial' => 'FUJ-' . uniqid(),
                ],
            ],
        ]);

        $responseEdit->assertRedirect(route('admin.devices.index'));

        // La impresora debe estar ahora en stock (desvinculada)
        $printer->refresh();
        $this->assertNull($printer->current_asset_id);
        $this->assertEquals('in_stock', $printer->status);

        // El escáner debe estar ahora asociado al activo
        $scanner = InventoryPeripheral::where('current_asset_id', $asset->id)
            ->where('type', 'scanner')
            ->first();
        $this->assertNotNull($scanner, 'El escáner adicional debe estar asociado al activo');
        $this->assertEquals('Fujitsu', $scanner->brand);
    }

    /**
     * Valida que en /admin/devices se haya erradicado por completo la palabra 'SLOT #'
     * y 'Slot #' de todos los modales (detalle, edición, historial) y vistas.
     */
    public function test_admin_devices_views_eradicate_slot_label(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get(route('admin.devices.index'));
        $response->assertStatus(200);

        $response->assertDontSee('SLOT #', false);
        $response->assertDontSee('Slot #', false);
        $response->assertDontSee('det-device-slot', false);
        $response->assertDontSee('edit-device-slot', false);
        $response->assertDontSee('hist-device-slot', false);
    }

    /**
     * Valida que el Router Principal (ID 1) mantenga estrictamente sus parámetros legítimos
     * en MariaDB: tipo 'router', protocolo 'TELNET' puerto 23, IP 10.20.23.1 y notas oficiales.
     */
    public function test_router_principal_in_mariadb_maintains_telnet_and_router_integrity(): void
    {
        $asset = InventoryAsset::find(1);
        $this->assertNotNull($asset, 'El Router Principal (ID 1) debe existir en inventory_assets');
        $this->assertEquals('ROUTER PRINCIPAL', $asset->name);
        $this->assertEquals('router', $asset->type);
        $this->assertEquals('TELNET', $asset->access_type);
        $this->assertEquals(23, $asset->access_port);
        $this->assertEquals('10.20.23.1', $asset->ip_address);
        $this->assertEquals('Router Gateway principal de la Sede Valle Seco.', $asset->notes);

        $netDev = \App\Models\MonitoredNetworkDevice::find(1);
        $this->assertNotNull($netDev, 'El Router Principal (ID 1) debe existir en monitored_network_devices');
        $this->assertEquals('ROUTER PRINCIPAL', $netDev->name);
        $this->assertEquals('TELNET', $netDev->access_type);
        $this->assertEquals(23, $netDev->access_port);
        $this->assertEquals('10.20.23.1', $netDev->ip);
        $this->assertEquals('Router Gateway principal de la Sede Valle Seco.', $netDev->notes);
    }

    /**
     * Valida que el controlador unificado de visibilidad por tipo (applyDeviceTypeVisibility)
     * esté presente y vinculado con 'onchange' tanto en el selector de creación como de edición,
     * garantizando que las secciones modulares se oculten y muestren uniformemente.
     */
    public function test_device_type_visibility_controller_present_for_both_modals(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get(route('admin.devices.index'));
        $response->assertStatus(200);

        // Controladores JS reactivos
        $response->assertSee('applyDeviceTypeVisibility', false);
        $response->assertSee('handleCreateDeviceTypeChange', false);
        $response->assertSee('handleEditDeviceTypeChange', false);

        // Disparadores en ambos selectores
        $response->assertSee('onchange="handleCreateDeviceTypeChange(this.value)"', false);
        $response->assertSee('onchange="handleEditDeviceTypeChange(this.value)"', false);

        // IDs de contenedores modulares para visibilidad reactiva
        $response->assertSee('create-section-peripherals', false);
        $response->assertSee('edit-section-peripherals', false);
        $response->assertSee('create-assignment-wrapper', false);
        $response->assertSee('edit-assignment-wrapper', false);
        $response->assertSee('create-section-monitoring', false);
        $response->assertSee('edit-section-monitoring', false);
    }

    /**
     * Valida el soporte dual de acceso remoto (SSH+VNC) y su visualización en la tabla de dispositivos.
     */
    public function test_dual_ssh_vnc_access_support_and_rendering(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        $uniqueName = 'TEST DUAL PC ' . uniqid();
        $ip = '10.20.99.' . rand(10, 250);

        $response = $this->actingAs($user)->post(route('admin.devices.store'), [
            'monitored_site_id' => $site->id,
            'name' => $uniqueName,
            'type' => 'desktop',
            'status' => 'assigned',
            'ip' => $ip,
            'access_type' => 'SSH+VNC',
            'access_port' => null,
        ]);

        $response->assertRedirect(route('admin.devices.index'));

        $asset = InventoryAsset::where('name', $uniqueName)->first();
        $this->assertNotNull($asset);
        $this->assertEquals('SSH+VNC', $asset->access_type);
        $this->assertEquals(22, $asset->access_port);

        $this->assertEquals(22, $asset->ssh_port);
        $this->assertEquals(5900, $asset->vnc_port);

        $viewResponse = $this->actingAs($user)->get(route('admin.devices.index', ['search' => $ip]));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Consola SSH Web (' . $ip . ':22)', false);
        $viewResponse->assertSee('Conectar Escritorio Remoto VNC (' . $ip . ':5900)', false);
        $viewResponse->assertSee('Dual: SSH (22) + VNC (5900)', false);
    }

    /**
     * Valida la persistencia de puertos personalizados y credenciales cifradas VNC.
     */
    public function test_can_persist_custom_multi_protocol_ports_and_vnc_credentials(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user);

        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        $ip = '10.20.77.' . rand(10, 250);
        $uniqueName = 'TEST-CUSTOM-PORTS-' . uniqid();

        $response = $this->actingAs($user)->post(route('admin.devices.store'), [
            'monitored_site_id' => $site->id,
            'name' => $uniqueName,
            'type' => 'desktop',
            'status' => 'assigned',
            'ip' => $ip,
            'access_type' => 'SSH+VNC',
            'ssh_port' => 2222,
            'vnc_port' => 5902,
            'vnc_password' => 'SecretVnc123!',
        ]);

        $response->assertRedirect(route('admin.devices.index'));

        $asset = InventoryAsset::where('name', $uniqueName)->first();
        $this->assertNotNull($asset);
        $this->assertEquals(2222, $asset->ssh_port);
        $this->assertEquals(5902, $asset->vnc_port);
        $this->assertTrue($asset->has_vnc_credentials);
        $this->assertEquals('SecretVnc123!', $asset->vnc_password_encrypted);

        $viewResponse = $this->actingAs($user)->get(route('admin.devices.index', ['search' => $ip]));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Consola SSH Web (' . $ip . ':2222)', false);
        $viewResponse->assertSee('Conectar Escritorio Remoto VNC (' . $ip . ':5902)', false);
        $viewResponse->assertSee('title="Clave de autenticación VNC cifrada configurada"', false);
    }

    /**
     * Valida la inferencia automática de acceso remoto en GlpiAssetSyncService
     * asignando SSH+VNC (22) a sistemas Linux y VNC (5900) a sistemas Windows.
     */
    public function test_glpi_sync_infers_remote_access_protocol(): void
    {
        $normalizer = app(\App\Services\Glpi\GlpiHardwareNormalizer::class);
        $syncService = app(\App\Services\Glpi\GlpiAssetSyncService::class);

        // Caso Linux
        $linuxIp = '10.20.99.' . rand(10, 250);
        $normLinux = $normalizer->normalize([
            'hardware' => ['name' => 'TEST-LINUX-' . uniqid(), 'osname' => 'Ubuntu 22.04 LTS (x86_64)'],
            'bios' => ['ssn' => 'SN-LINUX-' . uniqid()],
        ], $linuxIp);
        $resLinux = $syncService->sync($normLinux);
        $linuxAsset = $resLinux['asset'];
        $this->assertNotNull($linuxAsset);
        $this->assertEquals('SSH+VNC', $linuxAsset->access_type);
        $this->assertEquals(22, $linuxAsset->access_port);
        $this->assertEquals(22, $linuxAsset->ssh_port);
        $this->assertEquals(5900, $linuxAsset->vnc_port);

        // Caso Windows
        $winIp = '10.20.99.' . rand(10, 250);
        $normWin = $normalizer->normalize([
            'hardware' => ['name' => 'TEST-WIN-' . uniqid(), 'osname' => 'Microsoft Windows 7 Ultimate'],
            'bios' => ['ssn' => 'SN-WIN-' . uniqid()],
        ], $winIp);
        $resWin = $syncService->sync($normWin);
        $winAsset = $resWin['asset'];
        $this->assertNotNull($winAsset);
        $this->assertEquals('VNC', $winAsset->access_type);
        $this->assertEquals(5900, $winAsset->access_port);
        $this->assertEquals(5900, $winAsset->vnc_port);

        // Caso Impresora
        $printerIp = '10.20.99.' . rand(10, 250);
        $normPrinter = $normalizer->normalize([
            'hardware' => ['name' => 'TEST-PRINTER-' . uniqid()],
            'type' => 'printer',
        ], $printerIp);
        $resPrinter = $syncService->sync($normPrinter);
        $printerAsset = $resPrinter['asset'];
        $this->assertNotNull($printerAsset);
        $this->assertEquals('WEB', $printerAsset->access_type);
        $this->assertEquals(80, $printerAsset->access_port);
        $this->assertEquals(80, $printerAsset->web_port);
    }

    /**
     * Valida la inferencia automática de acceso WEB (80) al catalogar/promover una impresora descubierta.
     */
    public function test_promote_printer_to_asset_infers_web_access(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $user = User::where('role', 'admin')->first();
        $site = MonitoredSite::first();

        $printerIp = '10.20.88.' . rand(10, 250);
        $printerName = 'TEST-PRINTER-' . uniqid();

        $response = $this->actingAs($user)->post(route('admin.inventory.deploy.promote-asset'), [
            'monitored_site_id' => $site->id,
            'ip' => $printerIp,
            'name' => $printerName,
            'type' => 'printer',
            'brand' => 'Canon Inc.',
        ]);

        $response->assertJson(['success' => true]);

        $asset = InventoryAsset::where('ip_address', $printerIp)->first();
        $this->assertNotNull($asset);
        $this->assertEquals('WEB', $asset->access_type);
        $this->assertEquals(80, $asset->access_port);
        $this->assertEquals(80, $asset->web_port);

        $netDev = \App\Models\MonitoredNetworkDevice::where('ip', $printerIp)->first();
        $this->assertNotNull($netDev);
        $this->assertEquals('WEB', $netDev->access_type);
        $this->assertEquals(80, $netDev->access_port);
        $this->assertEquals(80, $netDev->web_port);
    }
}


