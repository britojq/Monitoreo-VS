<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminCustodianReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first() ?? User::first();
    }

    public function test_search_ldap_requires_authentication(): void
    {
        $response = $this->getJson(route('admin.inventory.custodian.search-ldap', ['q' => 'Brito']));
        $this->assertTrue(in_array($response->status(), [401, 302, 403]));
    }

    public function test_search_ldap_returns_results_for_authenticated_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.inventory.custodian.search-ldap', ['q' => 'Brito']));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'data',
        ]);
    }

    public function test_reconcile_custodian_updates_asset_and_preserves_pdf_scan(): void
    {
        // 1. Obtener o crear un activo legítimo de prueba
        $asset = InventoryAsset::firstOrCreate(
            ['asset_tag' => 'NET-TEST-RECON-01'],
            [
                'name' => 'PC TEST RECON',
                'type' => 'desktop',
                'status' => 'assigned',
                'custodian_name' => 'NOMBRE ANTIGUO CON ERROR',
                'custodian_id_number' => '12345678',
                'custodian_department' => 'DEPARTAMENTO OBSOLETO',
                'is_active' => true,
            ]
        );

        // 2. Asociar un acta con documento escaneado existente
        $existingPdfPath = 'assignments/scans/acta_test_original_preservada.pdf';
        $assignment = InventoryAssignment::firstOrCreate(
            ['assignment_code' => 'UINF-4.05-TEST-9999'],
            [
                'inventory_asset_id' => $asset->id,
                'user_id' => $this->adminUser->id,
                'custodian_name' => 'NOMBRE ANTIGUO CON ERROR',
                'custodian_id_number' => '12345678',
                'custodian_department' => 'DEPARTAMENTO OBSOLETO',
                'custodian_role' => 'Personal',
                'movement_type' => 'assignment',
                'assigned_at' => now(),
                'document_pdf_path' => $existingPdfPath,
                'status' => 'active',
                'delivered_by_user_id' => $this->adminUser->id,
            ]
        );

        // 3. Ejecutar la conciliación oficial vía API
        $payload = [
            'asset_id' => $asset->id,
            'custodian_name' => 'JUAN CARLOS PEREZ GOMEZ',
            'custodian_id_number' => '19876543',
            'custodian_department' => 'GPO TRAB INFRA TECNOL CARABOBO',
            'custodian_role' => 'Analista de Infraestructura',
            'phone_extension' => '54321',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.inventory.custodian.reconcile'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'asset_id' => $asset->id,
                'custodian_name' => 'JUAN CARLOS PEREZ GOMEZ',
                'custodian_id_number' => '19876543',
                'custodian_department' => 'GPO TRAB INFRA TECNOL CARABOBO',
                'has_scan' => true,
            ],
        ]);

        // 4. Verificar integridad en base de datos
        $asset->refresh();
        $this->assertEquals('JUAN CARLOS PEREZ GOMEZ', $asset->custodian_name);
        $this->assertEquals('19876543', $asset->custodian_id_number);
        $this->assertEquals('GPO TRAB INFRA TECNOL CARABOBO', $asset->custodian_department);

        $assignment->refresh();
        $this->assertEquals('JUAN CARLOS PEREZ GOMEZ', $assignment->custodian_name);
        $this->assertEquals('19876543', $assignment->custodian_id_number);
        $this->assertEquals('GPO TRAB INFRA TECNOL CARABOBO', $assignment->custodian_department);
        // ¡PRESERVACIÓN CRÍTICA DEL ARCHIVO DIGITALIZADO!
        $this->assertEquals($existingPdfPath, $assignment->document_pdf_path);
    }

    public function test_devices_view_renders_successfully_with_reconcile_modal(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.devices.index'));

        $response->assertStatus(200);
        $response->assertSee('reconcileLdapModal');
        $response->assertSee('openReconcileLdapModal');
        $response->assertSee('Conciliación con Directorio Activo (LDAP)');
    }

    public function test_assignments_index_view_renders_successfully_with_reconcile_modal(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.inventory.assignments.index'));

        $response->assertStatus(200);
        $response->assertSee('reconcileLdapModal');
        $response->assertSee('openReconcileLdapModal');
    }

    public function test_assignment_show_view_renders_successfully_with_reconcile_modal(): void
    {
        $assignment = InventoryAssignment::first();
        if ($assignment) {
            $response = $this->actingAs($this->adminUser)->get(route('admin.inventory.assignments.show', $assignment->id));

            $response->assertStatus(200);
            $response->assertSee('reconcileLdapModal');
            $response->assertSee('openReconcileLdapModal');
        }
    }
}
