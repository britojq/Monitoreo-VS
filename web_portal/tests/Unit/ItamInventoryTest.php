<?php

namespace Tests\Unit;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponent;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use App\Models\MaintenanceTicket;
use App\Models\GlpiInventoryLog;
use App\Services\PermissionService;
use PHPUnit\Framework\TestCase;

class ItamInventoryTest extends TestCase
{
    /**
     * Prueba los accessors y mutators de InventoryAsset.
     */
    public function test_inventory_asset_attributes_and_accessors(): void
    {
        $asset = new InventoryAsset([
            'asset_tag' => 'TEST-PC-001',
            'name' => 'PC-ADMINISTRACION-01',
            'type' => 'desktop',
            'status' => 'in_stock',
        ]);

        $this->assertEquals('desktop_windows', $asset->type_icon);
        $this->assertEquals('En Bóveda (Disponible)', $asset->status_label);

        $asset->type = 'server';
        $this->assertEquals('dns', $asset->type_icon);

        $asset->status = 'assigned';
        $this->assertEquals('Asignado (En Uso)', $asset->status_label);
    }

    /**
     * Prueba los iconos de componentes internos de hardware.
     */
    public function test_inventory_component_type_icons(): void
    {
        $cpu = new InventoryComponent(['type' => 'cpu']);
        $this->assertEquals('memory', $cpu->type_icon);

        $ram = new InventoryComponent(['type' => 'ram']);
        $this->assertEquals('developer_board', $ram->type_icon);

        $disk = new InventoryComponent(['type' => 'disk']);
        $this->assertEquals('hard_drive', $disk->type_icon);
    }

    /**
     * Prueba los accessors de periféricos independientes.
     */
    public function test_inventory_peripheral_attributes(): void
    {
        $monitor = new InventoryPeripheral([
            'type' => 'monitor',
            'status' => 'assigned',
        ]);

        $this->assertEquals('monitor', $monitor->type_icon);
        $this->assertEquals('Asignado', $monitor->status_label);

        $keyboard = new InventoryPeripheral(['type' => 'keyboard', 'status' => 'in_stock']);
        $this->assertEquals('keyboard', $keyboard->type_icon);
        $this->assertEquals('En Stock (Disponible)', $keyboard->status_label);
    }

    /**
     * Prueba los labels de tipos de movimiento de asignación.
     */
    public function test_inventory_assignment_movement_type_labels(): void
    {
        $asg = new InventoryAssignment(['movement_type' => 'assignment']);
        $this->assertEquals('Asignación Inicial', $asg->movement_type_label);

        $transfer = new InventoryAssignment(['movement_type' => 'transfer']);
        $this->assertEquals('Movilización / Traslado', $transfer->movement_type_label);

        $return = new InventoryAssignment(['movement_type' => 'return']);
        $this->assertEquals('Devolución / Desincorporación', $return->movement_type_label);
    }

    /**
     * Prueba los labels de tickets de mantenimiento.
     */
    public function test_maintenance_ticket_labels(): void
    {
        $ticket = new MaintenanceTicket([
            'type' => 'preventive',
            'status' => 'scheduled',
        ]);

        $this->assertEquals('Preventivo', $ticket->type_label);
        $this->assertEquals('Programado', $ticket->status_label);

        $ticket->status = 'completed';
        $this->assertEquals('Finalizado', $ticket->status_label);
    }

    /**
     * Prueba que PermissionService contenga los permisos de inventario ITAM.
     */
    public function test_permission_service_includes_inventory_module(): void
    {
        $keys = PermissionService::getAllPermissionKeys();

        $this->assertContains('inventory.view', $keys);
        $this->assertContains('inventory.manage', $keys);
        $this->assertContains('inventory.assign', $keys);
        $this->assertContains('inventory.maintenance', $keys);

        $modules = PermissionService::getModules();
        $this->assertArrayHasKey('inventory', $modules);
        $this->assertEquals('Gestión de Activos de TI (ITAM)', $modules['inventory']['name']);

        $defaultOps = PermissionService::getDefaultOperatorPermissions();
        $this->assertContains('inventory.view', $defaultOps);
        $this->assertNotContains('inventory.manage', $defaultOps);
    }

    /**
     * Prueba el descarte de números de serie genéricos o no asignados por el fabricante.
     */
    public function test_glpi_service_identifies_generic_serials(): void
    {
        $notifier = $this->createMock(\App\Services\TelegramNotificationService::class);
        $service = new \App\Services\GlpiInventoryService($notifier);

        $this->assertTrue($service->isGenericSerial(null));
        $this->assertTrue($service->isGenericSerial(''));
        $this->assertTrue($service->isGenericSerial('To be filled by O.E.M.'));
        $this->assertTrue($service->isGenericSerial('Default string'));
        $this->assertTrue($service->isGenericSerial('none'));
        $this->assertTrue($service->isGenericSerial('0123456789'));
        $this->assertTrue($service->isGenericSerial('1234567890'));

        $this->assertFalse($service->isGenericSerial('L1HF98Z0123'));
        $this->assertFalse($service->isGenericSerial('CND21849XY'));
        $this->assertFalse($service->isGenericSerial('VMware-42 1b 3c 4d'));
    }

    /**
     * Prueba la normalización de la estructura de datos emitida por un Agente GLPI.
     */
    public function test_glpi_service_normalizes_agent_payload(): void
    {
        $notifier = $this->createMock(\App\Services\TelegramNotificationService::class);
        $service = new \App\Services\GlpiInventoryService($notifier);

        $payload = [
            'content' => [
                'hardware' => [
                    'name' => 'LAPTOP-VENTAS-02',
                    'chassis_type' => 'Notebook',
                    'osname' => 'Windows 11 Pro',
                    'osversion' => '23H2',
                ],
                'bios' => [
                    'smanufacturer' => 'HP',
                    'smodel' => 'ProBook 450 G8',
                    'ssn' => '5CD1234XYZ',
                ],
                'networks' => [
                    [
                        'mac' => 'A0:B1:C2:D3:E4:F5',
                        'ipaddress' => '192.168.1.150',
                        'status' => 'up',
                    ]
                ],
                'cpus' => [
                    ['name' => '11th Gen Intel(R) Core(TM) i7-1165G7 @ 2.80GHz', 'cores' => 4]
                ],
                'memories' => [
                    ['capacity' => 16384, 'caption' => 'SODIMM 1']
                ],
                'storages' => [
                    ['model' => 'KIOXIA 512GB NVMe', 'disksize' => 512000]
                ]
            ]
        ];

        $norm = $service->normalizeInventoryData($payload, '127.0.0.1');

        $this->assertEquals('LAPTOP-VENTAS-02', $norm['name']);
        $this->assertEquals('5CD1234XYZ', $norm['serial_number']);
        $this->assertEquals('HP', $norm['brand']);
        $this->assertEquals('ProBook 450 G8', $norm['model']);
        $this->assertEquals('laptop', $norm['type']);
        $this->assertEquals('A0:B1:C2:D3:E4:F5', $norm['mac_address']);
        $this->assertEquals('192.168.1.150', $norm['ip_address']);
        $this->assertEquals('Windows 11 Pro 23H2', $norm['operating_system']);
        $this->assertCount(1, $norm['cpus']);
        $this->assertCount(1, $norm['memories']);
        $this->assertCount(1, $norm['drives']);
    }
}

