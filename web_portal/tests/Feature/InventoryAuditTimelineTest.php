<?php

namespace Tests\Feature;

use App\Contracts\AssetTimelineInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponentHistory;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryAuditTimelineTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $operatorUser;
    protected User $unauthorizedUser;
    protected MonitoredSite $testSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_audit_timeline_test@valle-seco.local'],
            [
                'name' => 'Admin Timeline Tester',
                'role' => 'admin',
                'is_active' => true,
                'terms_accepted' => true,
                'password' => bcrypt('password123'),
            ]
        );
        $this->adminUser->update(['is_active' => true]);

        $this->operatorUser = User::firstOrCreate(
            ['email' => 'operator_audit_timeline_test@valle-seco.local'],
            [
                'name' => 'Operador Timeline Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => ['inventory.view'],
                'password' => bcrypt('password123'),
            ]
        );
        $this->operatorUser->update(['is_active' => true]);

        $this->unauthorizedUser = User::firstOrCreate(
            ['email' => 'unauth_audit_timeline_test@valle-seco.local'],
            [
                'name' => 'Usuario No Autorizado Timeline',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => [], // Sin permisos
                'password' => bcrypt('password123'),
            ]
        );
        $this->unauthorizedUser->update(['is_active' => true]);

        $this->testSite = MonitoredSite::firstOrCreate(
            ['name' => 'SEDE TEST TIMELINE'],
            [
                'ip' => '10.20.96.1',
                'is_active' => true,
                'sort_order' => 99,
            ]
        );
    }

    public function test_admin_and_authorized_operator_can_access_inventory_audit_console(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.inventory.audit.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.inventory.audit.index');
        $response->assertSee('Consola de Auditoría &amp; Trazabilidad ITAM', false);
        $response->assertSee('Eventos ITAM');
        $response->assertSee('Hardware Drifts');

        // Cumplimiento estricto de Regla de Oro #9 y Error Histórico #7
        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
        $response->assertDontSee('SLOT #');
        $response->assertDontSee('Ollama');

        // Operador con permiso inventory.view
        $respOperator = $this->actingAs($this->operatorUser)->get(route('admin.inventory.audit.index'));
        $respOperator->assertStatus(200);
    }

    public function test_unauthorized_user_without_permission_receives_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.inventory.audit.index'));
        $response->assertStatus(403);
    }

    public function test_timeline_service_consolidates_events_for_asset(): void
    {
        // Crear activo de cómputo efímero
        $asset = InventoryAsset::create([
            'asset_tag' => 'AST-TIMELINE-' . uniqid(),
            'name' => 'ESTACIÓN AUDITORÍA FORENSE', // Nombre Etiqueta (SSOT)
            'type' => 'desktop',
            'status' => 'assigned',
            'ip_address' => '10.20.96.45',
            'serial_number' => 'SN-TL-' . uniqid(),
            'brand' => 'HP',
            'model' => 'EliteDesk 800 G3',
            'monitored_site_id' => $this->testSite->id,
            'custodian_name' => 'Carlos Mendoza',
        ]);

        // Crear asignación UINF-4.05 efímera
        $assignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-TEST-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'user_id' => $this->adminUser->id,
            'custodian_name' => 'Carlos Mendoza',
            'custodian_department' => 'Auditoría Interna',
            'monitored_site_id' => $this->testSite->id,
            'movement_type' => 'assignment',
            'assigned_at' => now()->subDays(5),
            'status' => 'active',
        ]);

        // Crear histórico de drift de componente efímero
        $drift = InventoryComponentHistory::create([
            'inventory_asset_id' => $asset->id,
            'action' => 'drift_detected',
            'component_type' => 'ram',
            'old_value' => ['capacity' => '8 GB'],
            'new_value' => ['capacity' => '16 GB'],
            'reason' => 'Sondeo en caliente detectó aumento de memoria RAM',
            'registered_by_user_id' => $this->adminUser->id,
            'recorded_at' => now()->subDays(2),
        ]);

        // Crear registro de auditoría en audit_logs
        AuditLog::create([
            'user_id' => $this->adminUser->id,
            'user_name' => $this->adminUser->name,
            'event' => 'approved',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->name,
            'entity_label' => $asset->asset_tag,
            'description' => "Equipo aprobado e incorporado por auditoría",
            'ip_address' => '127.0.0.1',
        ]);

        /** @var AssetTimelineInterface $service */
        $service = app(AssetTimelineInterface::class);
        $timeline = $service->getAssetTimeline($asset);

        $this->assertGreaterThanOrEqual(3, $timeline->count());

        // Verificar presencia del evento de asignación
        $assignEvent = $timeline->firstWhere('id', 'assign_' . $assignment->id);
        $this->assertNotNull($assignEvent);
        $this->assertEquals('assignment', $assignEvent['type']);
        $this->assertStringContainsString($assignment->assignment_code, $assignEvent['title']);
        $this->assertStringContainsString('Carlos Mendoza', $assignEvent['description']);

        // Verificar presencia del evento de drift
        $driftEvent = $timeline->firstWhere('id', 'drift_' . $drift->id);
        $this->assertNotNull($driftEvent);
        $this->assertEquals('drift', $driftEvent['type']);
        $this->assertStringContainsString('RAM', $driftEvent['title']);
        $this->assertStringContainsString('aumento de memoria', $driftEvent['description']);

        // Verificar orden cronológico inverso (el más reciente primero)
        $timestamps = $timeline->pluck('timestamp')->map(fn($t) => $t->timestamp)->toArray();
        $sortedTimestamps = $timestamps;
        rsort($sortedTimestamps);
        $this->assertEquals($sortedTimestamps, $timestamps);
    }

    public function test_asset_show_view_includes_timeline_tab_and_data(): void
    {
        $asset = InventoryAsset::create([
            'asset_tag' => 'AST-VIEW-' . uniqid(),
            'name' => 'ESTACIÓN RECEPCIÓN 01', // Nombre Etiqueta (SSOT)
            'type' => 'desktop',
            'status' => 'assigned',
            'ip_address' => '10.20.96.46',
            'serial_number' => 'SN-VW-' . uniqid(),
            'brand' => 'Dell',
            'model' => 'OptiPlex 7050',
            'monitored_site_id' => $this->testSite->id,
            'custodian_name' => 'Luisana Rondón',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.inventory.assets.show', $asset->id));

        $response->assertStatus(200);
        $response->assertViewHas('timeline');
        $response->assertSee('Trazabilidad Forense');
        $response->assertSee('Línea de Tiempo Cronológica Unificada');

        // Cumplimiento estricto de Regla de Oro #9
        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
    }

    public function test_asset_timeline_json_endpoint_returns_data(): void
    {
        $asset = InventoryAsset::create([
            'asset_tag' => 'AST-JSON-' . uniqid(),
            'name' => 'ESTACIÓN SERVICIO TÉCNICO',
            'type' => 'desktop',
            'status' => 'in_stock',
            'ip_address' => '10.20.96.47',
            'serial_number' => 'SN-JSON-' . uniqid(),
            'brand' => 'Lenovo',
            'model' => 'ThinkCentre M720',
            'monitored_site_id' => $this->testSite->id,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.inventory.assets.timeline', $asset->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'asset_tag' => $asset->asset_tag,
            ],
        ]);
        $response->assertJsonStructure([
            'success',
            'asset' => ['id', 'name', 'asset_tag', 'serial_number', 'status', 'status_label'],
            'timeline',
            'total_events',
        ]);
    }

    public function test_inventory_audit_export_returns_streamed_csv_and_logs_audit(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.inventory.audit.export', ['format' => 'csv']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('trazabilidad_itam_', $response->headers->get('content-disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'inventory',
            'event' => 'exported',
        ]);
    }
}
