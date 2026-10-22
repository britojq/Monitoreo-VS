<?php

namespace Tests\Feature;

use App\Contracts\GlpiAgentTriggerInterface;
use App\Contracts\HardwareDriftDetectorInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryComponent;
use App\Models\InventoryComponentHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GlpiHardwareDriftTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $operatorUser;
    protected InventoryAsset $testAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_drift_test@valle-seco.local'],
            [
                'name' => 'Admin Drift Tester',
                'role' => 'admin',
                'is_active' => true,
                'terms_accepted' => true,
                'password' => bcrypt('password123'),
            ]
        );
        $this->adminUser->update(['is_active' => true]);

        $this->operatorUser = User::firstOrCreate(
            ['email' => 'operator_drift_test@valle-seco.local'],
            [
                'name' => 'Operador Drift Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => ['inventory.view'],
                'password' => bcrypt('password123'),
            ]
        );
        $this->operatorUser->update(['is_active' => true]);

        // Crear activo de prueba efímero
        $this->testAsset = InventoryAsset::create([
            'asset_tag' => 'AST-DRIFT-' . uniqid(),
            'name' => 'EQUIPO DRIFT TEST',
            'type' => 'desktop',
            'status' => 'assigned',
            'ip_address' => '192.168.1.245',
            'mac_address' => '00:aa:bb:cc:dd:ee',
            'monitored_site_id' => 1,
            'custodian_name' => 'Funcionario Prueba Drift',
        ]);
    }

    /**
     * Test 1: Detector identifica correctamente reducciones y sustracciones de hardware como Drift.
     */
    public function test_hardware_drift_detector_identifies_ram_and_disk_reductions(): void
    {
        $detector = app(HardwareDriftDetectorInterface::class);

        // Sin historial de cambios, no debe haber drift
        $auditInitial = $detector->checkAssetDrift($this->testAsset->id);
        $this->assertFalse($auditInitial['has_drift']);

        // Simular evento forense de reducción de memoria RAM
        InventoryComponentHistory::create([
            'inventory_asset_id' => $this->testAsset->id,
            'action' => 'modified',
            'component_type' => 'ram',
            'old_value' => ['capacity' => '16 GB Total'],
            'new_value' => ['capacity' => '8 GB Total'],
            'reason' => '🚨 ALERTA FORENSE: Disminución de memoria RAM: Sustracción o falla de módulo RAM',
            'recorded_at' => now(),
        ]);

        // Ahora debe reportar drift de alta severidad
        $auditAfter = $detector->checkAssetDrift($this->testAsset->id);
        $this->assertTrue($auditAfter['has_drift']);
        $this->assertEquals('critical', $auditAfter['severity']);
        $this->assertEquals('ram', $auditAfter['primary_type']);

        $summary = $detector->getDriftSummary();
        $this->assertGreaterThanOrEqual(1, $summary['total_drift_count']);
        $this->assertGreaterThanOrEqual(1, $summary['ram_reductions']);
    }

    /**
     * Test 2: Regularización formal (acknowledge) limpia la alerta y registra en audit_logs.
     */
    public function test_drift_acknowledgement_clears_active_drift_and_logs_audit(): void
    {
        $detector = app(HardwareDriftDetectorInterface::class);

        // Crear evento de disco retirado
        InventoryComponentHistory::create([
            'inventory_asset_id' => $this->testAsset->id,
            'action' => 'removed',
            'component_type' => 'disk',
            'old_value' => ['name' => 'WD Blue 1TB'],
            'new_value' => null,
            'reason' => '🚨 ALERTA FORENSE: Disco retirado/desconectado',
            'recorded_at' => now()->subMinute(),
        ]);

        $this->assertTrue($detector->checkAssetDrift($this->testAsset->id)['has_drift']);

        // Administrador regulariza formalmente el evento
        $justification = 'Reemplazo programado por SSD de alta velocidad';
        $acknowledged = $detector->acknowledgeDrift($this->testAsset->id, $justification, $this->adminUser->id);
        $this->assertTrue($acknowledged);

        // Ahora el activo ya NO debe presentar alerta de drift activa
        $auditPostAck = $detector->checkAssetDrift($this->testAsset->id);
        $this->assertFalse($auditPostAck['has_drift']);

        // Verificar que existe el registro en la bitácora central de auditoría
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'hardware_drift_acknowledged',
            'auditable_id' => $this->testAsset->id,
        ]);
    }

    /**
     * Test 3: Endpoint AJAX de resumen de drift responde 200 con la estructura adecuada.
     */
    public function test_drift_summary_api_endpoint_returns_json_summary(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.inventory.deploy.drift-summary'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => [
                'total_drift_count',
                'ram_reductions',
                'missing_disks',
                'cpu_changes',
                'other_changes',
            ],
            'assets',
        ]);
    }

    /**
     * Test 4: Endpoint trigger-agent envía orden HTTP y maneja respuestas exitosas y de fallo.
     */
    public function test_trigger_agent_endpoint_handles_mocked_http_responses(): void
    {
        // 1. Simular agente remoto respondiendo 200 OK en puerto 62354
        Http::fake([
            '192.168.1.245:62354/now' => Http::response('OK: Inventory scheduled', 200),
        ]);

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.inventory.deploy.trigger-agent'), [
            'ip' => '192.168.1.245',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('Orden de inventario enviada exitosamente', $response->json('message'));

        // 2. Simular agente inalcanzable (puerto cerrado)
        Http::fake([
            '192.168.1.246:62354/now' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $failResponse = $this->actingAs($this->adminUser)->postJson(route('admin.inventory.deploy.trigger-agent'), [
            'ip' => '192.168.1.246',
        ]);

        $failResponse->assertStatus(422);
        $this->assertFalse($failResponse->json('success'));
    }

    /**
     * Test 5: Blindaje RBAC: Operador sin permiso inventory.deploy recibe 403 al intentar disparar agente.
     */
    public function test_operator_without_deploy_permission_cannot_trigger_agent(): void
    {
        $response = $this->actingAs($this->operatorUser)->postJson(route('admin.inventory.deploy.trigger-agent'), [
            'ip' => '192.168.1.245',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 6: Cumplimiento de Regla de Oro #9: Cero términos vetados en las vistas modificadas.
     */
    public function test_zero_taboo_terms_in_views(): void
    {
        $deployView = file_get_contents(resource_path('views/admin/inventory/deploy/index.blade.php'));
        $showView = file_get_contents(resource_path('views/admin/inventory/assets/show.blade.php'));

        $this->assertStringNotContainsString('360°', $deployView);
        $this->assertStringNotContainsString('360°', $showView);
        $this->assertStringNotContainsString('custodio', strtolower($deployView));
    }
}
