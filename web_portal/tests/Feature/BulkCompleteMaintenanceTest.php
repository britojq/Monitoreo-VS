<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\MaintenanceTicket;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BulkCompleteMaintenanceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected MonitoredSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first();

        $this->site = MonitoredSite::first() ?? MonitoredSite::create([
            'name' => 'Sede Prueba Maint ' . uniqid(),
            'ip_range' => '192.168.1.0/24',
        ]);
    }

    public function test_bulk_complete_maintenance_marks_tickets_completed_and_restores_asset_status(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Activo asignado puesto en mantenimiento
        $assetAssigned = InventoryAsset::create([
            'name' => 'PC-MAINT-ASSIGNED-' . uniqid(),
            'asset_tag' => 'TAG-MAINT-1-' . uniqid(),
            'type' => 'desktop',
            'status' => 'maintenance',
            'custodian_name' => 'Pedro Perez',
            'monitored_site_id' => $this->site->id,
        ]);

        $ticket1 = MaintenanceTicket::create([
            'ticket_code' => 'OS-2026-' . rand(10000, 99999),
            'inventory_asset_id' => $assetAssigned->id,
            'title' => 'Mantenimiento preventivo',
            'type' => 'preventive',
            'priority' => 'medium',
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        // 2. Activo de infraestructura puesto en mantenimiento
        $assetStock = InventoryAsset::create([
            'name' => 'SW-MAINT-STOCK-' . uniqid(),
            'asset_tag' => 'TAG-MAINT-2-' . uniqid(),
            'type' => 'switch',
            'status' => 'maintenance',
            'monitored_site_id' => $this->site->id,
        ]);

        $ticket2 = MaintenanceTicket::create([
            'ticket_code' => 'OS-2026-' . rand(10000, 99999),
            'inventory_asset_id' => $assetStock->id,
            'title' => 'Limpieza de fuentes y soplado',
            'type' => 'preventive',
            'priority' => 'low',
            'status' => 'in_progress',
            'scheduled_at' => now(),
        ]);

        $response = $this->post(route('admin.inventory.assets.bulk-complete-maintenance'), [
            'asset_ids' => [$assetAssigned->id, $assetStock->id],
            'completed_at' => now()->format('Y-m-d'),
            'technician_id' => $this->adminUser->id,
            'resolution_notes' => 'Mantenimiento preventivo masivo completado con éxito: limpieza física y pruebas OK.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verificar ticket 1
        $ticket1->refresh();
        $this->assertEquals('completed', $ticket1->status);
        $this->assertNotNull($ticket1->completed_at);
        $this->assertEquals($this->adminUser->id, $ticket1->technician_user_id);
        $this->assertStringContainsString('limpieza física', $ticket1->resolution_notes);

        // Verificar activo 1 (vuelve a 'assigned')
        $assetAssigned->refresh();
        $this->assertEquals('assigned', $assetAssigned->status);

        // Verificar ticket 2
        $ticket2->refresh();
        $this->assertEquals('completed', $ticket2->status);

        // Verificar activo 2 (vuelve a 'active')
        $assetStock->refresh();
        $this->assertEquals('active', $assetStock->status);

        // Verificar auditoría
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $assetAssigned->id,
            'event' => 'updated',
        ]);
    }

    public function test_bulk_complete_creates_ticket_when_none_was_pre_scheduled(): void
    {
        $this->actingAs($this->adminUser);

        $asset = InventoryAsset::create([
            'name' => 'PC-MAINT-DIRECT-' . uniqid(),
            'asset_tag' => 'TAG-MAINT-DIRECT-' . uniqid(),
            'type' => 'desktop',
            'status' => 'maintenance',
            'custodian_name' => 'Maria Rodriguez',
            'monitored_site_id' => $this->site->id,
        ]);

        $response = $this->post(route('admin.inventory.assets.bulk-complete-maintenance'), [
            'asset_ids' => [$asset->id],
            'completed_at' => now()->format('Y-m-d'),
            'technician_id' => $this->adminUser->id,
            'resolution_notes' => 'Mantenimiento directo completado.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $asset->refresh();
        $this->assertEquals('assigned', $asset->status);

        $this->assertDatabaseHas('maintenance_tickets', [
            'inventory_asset_id' => $asset->id,
            'status' => 'completed',
            'resolution_notes' => 'Mantenimiento directo completado.',
        ]);
    }
}
