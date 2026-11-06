<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryComponent;
use App\Models\InventoryComponentHistory;
use App\Models\MonitoredSite;
use App\Models\User;
use App\Services\GlpiInventoryService;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GlpiForensicAlertTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected MonitoredSite $site;
    protected GlpiInventoryService $glpiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_forensic@test.local'],
            [
                'name' => 'Forensic Tester',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'status' => 'active',
                'terms_accepted' => true,
            ]
        );

        $this->site = MonitoredSite::where('name', 'CIAU VALLE SECO')->first() ?? MonitoredSite::first();

        $this->glpiService = app(GlpiInventoryService::class);
    }

    public function test_glpi_detects_removed_disk_and_logs_forensic_alert(): void
    {
        $uniqueSerial = 'FORENSIC-PC-' . uniqid();
        $diskSerial1 = 'DISK-AAA-' . uniqid();
        $diskSerial2 = 'DISK-BBB-' . uniqid();

        // Primer reporte con 2 discos
        $payload1 = [
            'action' => 'inventory',
            'deviceid' => 'DEV-' . $uniqueSerial,
            'content' => [
                'hardware' => [
                    'name' => 'PC-FORENSIC-' . $uniqueSerial,
                    'uuid' => 'UUID-' . $uniqueSerial,
                    'memory' => 16384,
                ],
                'bios' => [
                    'ssn' => $uniqueSerial,
                    'smanufacturer' => 'Dell Inc.',
                    'smodel' => 'OptiPlex 7050',
                ],
                'storages' => [
                    [
                        'name' => 'Samsung SSD 860 EVO 500GB',
                        'serial' => $diskSerial1,
                        'disksize' => 500107,
                    ],
                    [
                        'name' => 'Seagate Barracuda 1TB',
                        'serial' => $diskSerial2,
                        'disksize' => 1000204,
                    ],
                ],
            ],
        ];

        $res1 = $this->glpiService->processRawPayload(json_encode($payload1), '10.20.99.50');
        $this->assertEquals('ok', $res1['status']);
        $this->assertEquals('created', $res1['action']);

        $asset = InventoryAsset::where('serial_number', $uniqueSerial)->first();
        $this->assertNotNull($asset);

        // Verificar que los 2 discos están activos
        $activeDisks = $asset->components()->where('type', 'disk')->where('status', 'active')->count();
        $this->assertEquals(2, $activeDisks);

        // Segundo reporte: el segundo disco ya no está presente (sustracción / desconexión)
        $payload2 = [
            'action' => 'inventory',
            'deviceid' => 'DEV-' . $uniqueSerial,
            'content' => [
                'hardware' => [
                    'name' => 'PC-FORENSIC-' . $uniqueSerial,
                    'uuid' => 'UUID-' . $uniqueSerial,
                    'memory' => 16384,
                ],
                'bios' => [
                    'ssn' => $uniqueSerial,
                    'smanufacturer' => 'Dell Inc.',
                    'smodel' => 'OptiPlex 7050',
                ],
                'storages' => [
                    [
                        'name' => 'Samsung SSD 860 EVO 500GB',
                        'serial' => $diskSerial1,
                        'disksize' => 500107,
                    ],
                ],
            ],
        ];

        $res2 = $this->glpiService->processRawPayload(json_encode($payload2), '10.20.99.50');
        $this->assertEquals('ok', $res2['status']);
        $this->assertEquals('updated', $res2['action']);

        // El segundo disco debe estar marcado como 'removed'
        $removedDisk = InventoryComponent::where('inventory_asset_id', $asset->id)
            ->where('serial_number', $diskSerial2)
            ->first();

        $this->assertNotNull($removedDisk);
        $this->assertEquals('removed', $removedDisk->status);

        // Verificar que en el historial se registró la alerta forense
        $history = InventoryComponentHistory::where('inventory_asset_id', $asset->id)
            ->where('action', 'removed')
            ->where('component_type', 'disk')
            ->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString('Disco no reportado en nuevo inventario GLPI', $history->reason);
    }

    public function test_glpi_detects_ram_reduction_and_logs_forensic_alert(): void
    {
        $uniqueSerial = 'RAM-PC-' . uniqid();

        // Primer reporte: 16 GB RAM
        $payload1 = [
            'action' => 'inventory',
            'deviceid' => 'DEV-' . $uniqueSerial,
            'content' => [
                'hardware' => [
                    'name' => 'PC-RAM-' . $uniqueSerial,
                    'uuid' => 'UUID-' . $uniqueSerial,
                    'memory' => 16384,
                ],
                'bios' => [
                    'ssn' => $uniqueSerial,
                    'smanufacturer' => 'HP',
                    'smodel' => 'EliteDesk 800 G4',
                ],
            ],
        ];

        $res1 = $this->glpiService->processRawPayload(json_encode($payload1), '10.20.99.51');
        $this->assertEquals('ok', $res1['status']);
        $this->assertEquals('created', $res1['action']);

        $asset = InventoryAsset::where('serial_number', $uniqueSerial)->first();
        $this->assertNotNull($asset);

        // Segundo reporte: se extrajo un módulo, ahora tiene 8 GB RAM
        $payload2 = [
            'action' => 'inventory',
            'deviceid' => 'DEV-' . $uniqueSerial,
            'content' => [
                'hardware' => [
                    'name' => 'PC-RAM-' . $uniqueSerial,
                    'uuid' => 'UUID-' . $uniqueSerial,
                    'memory' => 8192,
                ],
                'bios' => [
                    'ssn' => $uniqueSerial,
                    'smanufacturer' => 'HP',
                    'smodel' => 'EliteDesk 800 G4',
                ],
            ],
        ];

        $res2 = $this->glpiService->processRawPayload(json_encode($payload2), '10.20.99.51');
        $this->assertEquals('ok', $res2['status']);
        $this->assertEquals('updated', $res2['action']);

        // Verificar que en el historial se registró la reducción forense de RAM
        $history = InventoryComponentHistory::where('inventory_asset_id', $asset->id)
            ->where('action', 'removed')
            ->where('component_type', 'ram')
            ->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString('Sustracción o falla de módulo RAM', $history->reason);
    }

    public function test_telegram_notification_service_methods_execute_cleanly(): void
    {
        $telegram = app(TelegramNotificationService::class);
        $this->assertNotNull($telegram);

        $asset = InventoryAsset::first();

        // Crear ticket
        $ticket = \App\Models\MaintenanceTicket::create([
            'ticket_code' => 'MNT-TEST-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'type' => 'preventive',
            'status' => 'scheduled',
            'priority' => 'medium',
            'title' => 'Mantenimiento Preventivo Trimestral',
            'technician_user_id' => $this->adminUser->id,
        ]);

        $telegram->notifyMaintenanceTicketOpened($ticket);

        // Crear asignación
        $assignment = \App\Models\InventoryAssignment::create([
            'assignment_code' => 'TEST-ACTA-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Juan Perez',
            'movement_type' => 'assignment',
            'provenance' => 'new',
            'assigned_at' => now(),
            'status' => 'active',
            'created_by' => $this->adminUser->id,
        ]);

        $telegram->notifyAssetAssignment($assignment);

        $this->assertTrue(true);
    }
}
