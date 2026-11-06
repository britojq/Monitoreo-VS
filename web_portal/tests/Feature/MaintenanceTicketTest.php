<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryComponentHistory;
use App\Models\MaintenanceTicket;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MaintenanceTicketTest extends TestCase
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
            ['serial_number' => 'MNT-ASSET-001'],
            [
                'asset_tag' => 'TAG-MNT-001',
                'name' => 'PC-TALLER-PRUEBAS',
                'type' => 'desktop',
                'brand' => 'Lenovo',
                'model' => 'M910s',
                'status' => 'active',
                'monitored_site_id' => $this->site->id,
            ]
        );
    }

    public function test_maintenance_index_page_is_accessible(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.maintenance.index'));

        $response->assertStatus(200);
        $response->assertSee('Módulo de Mantenimiento y Ciclo de Vida');
    }

    public function test_maintenance_create_page_is_accessible(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.maintenance.create'));

        $response->assertStatus(200);
        $response->assertSee('Abrir Orden de Servicio / Mantenimiento');
    }

    public function test_can_create_maintenance_ticket_and_generates_code(): void
    {
        $postData = [
            'inventory_asset_id' => $this->asset->id,
            'type' => 'corrective',
            'priority' => 'high',
            'title' => 'Falla en disco duro y lentitud extrema',
            'description' => 'El equipo presenta pantallazos azules y sectores defectuosos.',
            'scheduled_at' => now()->format('Y-m-d'),
            'technician_user_id' => $this->adminUser->id,
            'cost' => 45.00,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.maintenance.store'), $postData);

        $response->assertRedirect();

        $ticket = MaintenanceTicket::where('title', 'Falla en disco duro y lentitud extrema')->first();
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('MNT-', $ticket->ticket_code);
        $this->assertEquals('scheduled', $ticket->status);
        $this->assertEquals('in_maintenance', $this->asset->fresh()->status);
    }

    protected function createTestTicket(): MaintenanceTicket
    {
        return MaintenanceTicket::create([
            'ticket_code' => 'MNT-TEST-001',
            'inventory_asset_id' => $this->asset->id,
            'technician_user_id' => $this->adminUser->id,
            'type' => 'corrective',
            'status' => 'in_progress',
            'priority' => 'high',
            'title' => 'Falla en disco duro y lentitud extrema',
            'description' => 'El equipo presenta sectores defectuosos en SMART.',
        ]);
    }

    public function test_can_view_ticket_details(): void
    {
        $ticket = $this->createTestTicket();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.maintenance.show', $ticket->id));

        $response->assertStatus(200);
        $response->assertSee($ticket->ticket_code);
    }

    public function test_can_update_ticket_and_record_replaced_parts(): void
    {
        $ticket = $this->createTestTicket();

        $updateData = [
            'status' => 'completed',
            'priority' => 'high',
            'title' => 'Falla en disco duro y lentitud extrema - RESUELTO',
            'description' => 'Sustitución de disco duro mecánico por SSD.',
            'scheduled_at' => now()->format('Y-m-d'),
            'technician_user_id' => $this->adminUser->id,
            'resolution_notes' => 'Se instaló SSD Kingston 480GB, clonación exitosa y pruebas OK.',
            'cost' => 45.00,
            'warranty_applied' => 0,
            'parts_replaced' => [
                [
                    'name' => 'SSD Kingston 480GB A400',
                    'serial' => 'KNG-SSD-883921',
                    'cost' => 45.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.inventory.maintenance.update', $ticket->id), $updateData);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('completed', $ticket->status);
        $this->assertNotNull($ticket->completed_at);
        $this->assertCount(1, $ticket->parts_replaced_json);

        // Verifica que se restauró el estado del activo
        $this->assertNotEquals('in_maintenance', $ticket->asset->status);

        // Verifica que se guardó en el historial forense de componentes
        $history = InventoryComponentHistory::where('inventory_asset_id', $ticket->inventory_asset_id)
            ->where('action', 'replaced')
            ->first();
        $this->assertNotNull($history);
    }

    public function test_can_render_official_service_order_pdf(): void
    {
        $ticket = $this->createTestTicket();

        // Vista HTML
        $htmlResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.maintenance.pdf', $ticket->id));
        $htmlResponse->assertStatus(200);
        $htmlResponse->assertSee('ORDEN DE SERVICIO Y MANTENIMIENTO TÉCNICO');

        // Descarga PDF binaria
        $pdfResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.maintenance.pdf', ['ticket' => $ticket->id, 'download' => 1]));
        $pdfResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));
    }
}
