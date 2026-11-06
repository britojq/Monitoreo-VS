<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryAssignmentTest extends TestCase
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
            ['serial_number' => 'TEST-SN-999'],
            [
                'asset_tag' => 'AST-TEST-999',
                'name' => 'PC-TEST-AUTOMATION',
                'type' => 'desktop',
                'brand' => 'Lenovo',
                'model' => 'ThinkCentre',
                'status' => 'in_stock',
                'monitored_site_id' => $this->site->id,
            ]
        );
    }

    public function test_assignments_index_page_is_accessible(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.index'));

        $response->assertStatus(200);
        $response->assertSee('Mesa de Asignaciones, Devoluciones y Pases');
    }

    public function test_assignments_create_page_is_accessible(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.create'));

        $response->assertStatus(200);
        $response->assertSee('Registrar Asignación / Devolución / Movilización');
    }

    public function test_can_create_assignment_and_generates_official_code(): void
    {
        $postData = [
            'movement_type' => 'assignment',
            'inventory_asset_id' => $this->asset->id,
            'custodian_name' => 'Pedro Perez',
            'custodian_id_number' => 'V-12345678',
            'custodian_department' => 'Operaciones',
            'phone_extension' => '1050',
            'provenance' => 'stock',
            'assigned_at' => now()->format('Y-m-d'),
            'checkpoint_name' => 'Sede Valle Seco',
            'gate_operator_name' => 'Oficial Garita',
            'departure_time' => '10:00',
            'observations' => 'Entrega de equipo para labores administrativas',
            'peripherals' => [
                [
                    'type' => 'MONITOR',
                    'brand' => 'AOC',
                    'model' => '22B1HS',
                    'serial_number' => 'AOC12345',
                    'observations' => 'EN BUEN ESTADO'
                ]
            ],
            'specs' => [
                'processor' => 'Intel Core i5',
                'speed' => '3.0 GHz',
                'ram' => '8GB',
                'disk' => '500GB SSD',
                'os' => 'Debian 12',
                'device_name' => 'PC-PEDRO',
                'ip_address' => '192.168.1.50',
            ]
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assignments.store'), $postData);

        $response->assertRedirect();
        
        $assignment = InventoryAssignment::where('custodian_name', 'Pedro Perez')->first();
        $this->assertNotNull($assignment);
        $this->assertStringStartsWith('UINF-4.05-', $assignment->assignment_code);
        $this->assertEquals('assigned', $this->asset->fresh()->status);
        $this->assertEquals('Pedro Perez', $this->asset->fresh()->custodian_name);
    }

    protected function createTestAssignment(): InventoryAssignment
    {
        return InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-TEST-001',
            'inventory_asset_id' => $this->asset->id,
            'user_id' => $this->adminUser->id,
            'custodian_name' => 'Pedro Perez',
            'custodian_id_number' => 'V-12345678',
            'custodian_department' => 'Tecnología',
            'movement_type' => 'assignment',
            'provenance' => 'nuevo',
            'assigned_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_can_view_assignment_details(): void
    {
        $assignment = $this->createTestAssignment();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.show', $assignment->id));

        $response->assertStatus(200);
        $response->assertSee($assignment->assignment_code);
    }

    public function test_can_render_acta_uinf_html_and_download_pdf(): void
    {
        $assignment = $this->createTestAssignment();

        // 1. Vista web / HTML para impresión
        $htmlResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.pdf', $assignment->id));
        $htmlResponse->assertStatus(200);
        $htmlResponse->assertSee('UINF-4.05');
        $htmlResponse->assertSee('ASIGNACIÓN/DEVOLUCIÓN DE ACTIVOS DE INFORMACIÓN');

        // 2. Descarga PDF binaria
        $pdfResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.pdf', ['assignment' => $assignment->id, 'download' => 1]));
        $pdfResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    public function test_can_render_movilizacion_html_and_download_pdf(): void
    {
        $assignment = $this->createTestAssignment();

        // 1. Vista web / HTML
        $htmlResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.pdf-movilizacion', $assignment->id));
        $htmlResponse->assertStatus(200);
        $htmlResponse->assertSee('CONSTANCIA DE ENTRADA Y SALIDA DE EQUIPOS');

        // 2. Descarga PDF binaria
        $pdfResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.pdf-movilizacion', ['assignment' => $assignment->id, 'download' => 1]));
        $pdfResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    public function test_can_upload_scanned_physical_assignment_sheet(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('acta_fisica_firmada.pdf', 350, 'application/pdf');

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assets.upload-assignment-scan', $this->asset->id), [
                'custodian_name' => 'Carlos Mendoza',
                'custodian_id_number' => 'V-19876543',
                'custodian_department' => 'Transmisión',
                'assigned_at' => '2024-05-15',
                'control_number' => 'ACTA-FIS-2024-88',
                'observations' => 'Documento escaneado de asignación física previa',
                'scan_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('assigned', $this->asset->fresh()->status);
        $this->assertEquals('Carlos Mendoza', $this->asset->fresh()->custodian_name);

        $assignment = InventoryAssignment::where('inventory_asset_id', $this->asset->id)->first();
        $this->assertNotNull($assignment);
        $this->assertEquals('Carlos Mendoza', $assignment->custodian_name);
        $this->assertEquals('fisico_existente', $assignment->provenance);
        $this->assertNotNull($assignment->document_pdf_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($assignment->document_pdf_path);
    }

    public function test_create_assignment_honors_return_to_url(): void
    {
        $targetUrl = '/admin/inventory/assets?type=desktop&status=in_stock';

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.create', ['return_to' => $targetUrl]));

        $response->assertStatus(200);
        $response->assertSee(e($targetUrl), false);
    }

    public function test_show_assignment_displays_acta_de_asignacion_and_centered_preview(): void
    {
        $assignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-2026-9999',
            'inventory_asset_id' => $this->asset->id,
            'custodian_name' => 'Maria Rodriguez',
            'custodian_id_number' => 'V-18765432',
            'custodian_department' => 'Comercialización',
            'monitored_site_id' => $this->site->id,
            'movement_type' => 'assignment',
            'provenance' => 'stock',
            'assigned_at' => now(),
            'status' => 'active',
            'delivered_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.show', $assignment->id));

        $response->assertStatus(200);
        $response->assertSee('ACTA DE ASIGNACION', false);
        $response->assertSee('Código de Asignación:', false);
        $response->assertDontSee('Ficha de Acta & Pase Oficial', false);
        $response->assertDontSee('Código Oficial:', false);
        $response->assertSee('acta-preview-iframe', false);
        $response->assertSee('autoFitActaIframe', false);
        $response->assertSee('Responsable Asignado', false);
    }

    public function test_bulk_mobilization_creates_transfer_assignment_and_relocates_assets(): void
    {
        $targetSite = MonitoredSite::where('id', '!=', $this->site->id)->first() ?? MonitoredSite::first();

        $asset1 = InventoryAsset::create([
            'serial_number' => 'TEST-BULK-MOB-1-' . uniqid(),
            'asset_tag' => 'AST-MOB-01',
            'name' => 'PC-MOBILIZE-01',
            'type' => 'desktop',
            'brand' => 'Dell',
            'model' => 'OptiPlex 7050',
            'status' => 'in_stock',
            'monitored_site_id' => $this->site->id,
        ]);

        $asset2 = InventoryAsset::create([
            'serial_number' => 'TEST-BULK-MOB-2-' . uniqid(),
            'asset_tag' => 'AST-MOB-02',
            'name' => 'PC-MOBILIZE-02',
            'type' => 'desktop',
            'brand' => 'HP',
            'model' => 'ProDesk 400',
            'status' => 'in_stock',
            'monitored_site_id' => $this->site->id,
        ]);

        $postData = [
            'asset_ids' => [$asset1->id, $asset2->id],
            'monitored_site_id' => $targetSite->id,
            'custodian_name' => 'Funcionario Prueba Traslado',
            'custodian_id_number' => 'V-19876543',
            'custodian_department' => 'Soporte y Telecomunicaciones',
            'checkpoint_name' => 'Garita Principal Valle Seco',
            'gate_operator_name' => 'Vigilante Oficial',
            'departure_time' => '11:30',
            'assigned_at' => now()->format('Y-m-d'),
            'observations' => 'Traslado masivo de equipos para adecuación de puestos',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assets.bulk-mobilize'), $postData);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('bulk_mobilization_pdf');
        $response->assertSessionHas('bulk_mobilization_code');

        // Verificar que ambos activos fueron reubicados en la base de datos
        $this->assertEquals($targetSite->id, $asset1->fresh()->monitored_site_id);
        $this->assertEquals('Garita Principal Valle Seco', $asset1->fresh()->location_detail);
        $this->assertEquals($targetSite->id, $asset2->fresh()->monitored_site_id);
        $this->assertEquals('Garita Principal Valle Seco', $asset2->fresh()->location_detail);

        // Verificar el acta consolidada
        $assignment = InventoryAssignment::where('custodian_name', 'Funcionario Prueba Traslado')
            ->where('movement_type', 'transfer')
            ->latest('id')
            ->first();

        $this->assertNotNull($assignment);
        $this->assertStringStartsWith('PASE-MOV-', $assignment->assignment_code);
        $this->assertEquals($asset1->id, $assignment->inventory_asset_id);
        $this->assertIsArray($assignment->peripherals_json);

        $periphCollection = collect($assignment->peripherals_json);
        // Debe contener la torre del equipo secundario
        $this->assertTrue($periphCollection->contains('serial_number', $asset2->serial_number));
        // Debe contener los accesorios estándar del kit operativo (Teclado, Mouse, Protector)
        $this->assertTrue($periphCollection->contains('type', 'TECLADO'));
        $this->assertTrue($periphCollection->contains('type', 'MOUSE'));
        $this->assertTrue($periphCollection->contains('type', 'PROTECTOR DE VOLTAJE'));
    }

    public function test_bulk_mobilization_collects_real_peripherals_associated_in_mariadb(): void
    {
        $targetSite = MonitoredSite::first();

        $asset = InventoryAsset::create([
            'serial_number' => 'REAL-TEST-' . uniqid(),
            'asset_tag' => 'AST-REAL-' . uniqid(),
            'name' => 'PC-REAL-PERIPH',
            'type' => 'desktop',
            'status' => 'in_stock',
            'monitored_site_id' => $this->site->id,
        ]);

        $peripheral = \App\Models\InventoryPeripheral::create([
            'current_asset_id' => $asset->id,
            'type' => 'monitor',
            'brand' => 'ViewSonic',
            'model' => 'VA2456',
            'serial_number' => 'VS-MON-' . uniqid(),
            'status' => 'active',
        ]);

        $postData = [
            'asset_ids' => [$asset->id],
            'monitored_site_id' => $targetSite->id,
            'custodian_name' => 'Funcionario Con Perifericos',
            'include_standard_kit' => 1,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assets.bulk-mobilize'), $postData);

        $response->assertRedirect();

        $assignment = InventoryAssignment::where('custodian_name', 'Funcionario Con Perifericos')->latest('id')->first();
        $this->assertNotNull($assignment);

        $periphCollection = collect($assignment->peripherals_json);
        // Debe incluir el monitor real vinculado en MariaDB
        $this->assertTrue($periphCollection->contains('serial_number', $peripheral->serial_number));
        $this->assertTrue($periphCollection->contains('brand', 'ViewSonic'));
    }

    public function test_bulk_mobilization_via_json_returns_pdf_url(): void
    {
        $targetSite = MonitoredSite::first();

        $asset = InventoryAsset::create([
            'serial_number' => 'TEST-JSON-MOB-' . uniqid(),
            'asset_tag' => 'AST-JSON-01',
            'name' => 'PC-JSON-MOB',
            'type' => 'desktop',
            'brand' => 'Lenovo',
            'status' => 'in_stock',
            'monitored_site_id' => $targetSite->id,
        ]);

        $postData = [
            'asset_ids' => [$asset->id],
            'monitored_site_id' => $targetSite->id,
            'custodian_name' => 'Operador JSON',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.inventory.assets.bulk-mobilize'), $postData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'code',
            'pdf_url',
            'assignment_id',
        ]);
    }

    public function test_devices_index_renders_bulk_mobilization_modal_and_no_banned_words(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.devices.index'));

        $response->assertStatus(200);
        $response->assertSee('id="bulkMobilizationModal"', false);
        $response->assertSee('executeBulkMobilization', false);
        $response->assertSee('openSingleMobilizationModal', false);
        $response->assertSee('Funcionario Responsable del Traslado', false);
        $response->assertDontSee('360°', false);
        $response->assertDontSee('Ficha 360', false);
        $response->assertDontSee('Puesto 360', false);
        $response->assertDontSee('SLOT #', false);
    }

    public function test_movilizacion_pdf_fits_single_letter_page_and_omits_subtitles_and_placeholder_serials(): void
    {
        $targetSite = MonitoredSite::first();

        // 1. Crear asignación de prueba con 16 items (1 activo principal + 15 periféricos/accesorios)
        $asset = InventoryAsset::create([
            'serial_number' => 'REAL-SN-' . uniqid(),
            'asset_tag' => 'AST-PDF-' . uniqid(),
            'name' => 'PC-PDF-TEST',
            'type' => 'desktop',
            'brand' => 'HP',
            'model' => 'ProDesk 600',
            'status' => 'in_stock',
            'monitored_site_id' => $targetSite->id,
        ]);

        $mockPeriphs = [];
        for ($i = 1; $i <= 15; $i++) {
            $mockPeriphs[] = [
                'type' => ($i % 3 === 0 ? 'MONITOR' : ($i % 3 === 1 ? 'TECLADO' : 'MOUSE')),
                'brand' => 'HP',
                'model' => 'Standard',
                'serial_number' => ($i === 1 ? 'SN-PERIPH-01' : '[Kit operativo - Sin serial]'),
                'observations' => 'Asociado a PC-PDF-TEST',
            ];
        }

        $assignment = InventoryAssignment::create([
            'assignment_code' => 'PASE-TEST-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Funcionario Prueba A4',
            'custodian_id_number' => 'V-11223344',
            'monitored_site_id' => $targetSite->id,
            'movement_type' => 'transfer',
            'checkpoint_name' => 'Garita Principal',
            'gate_operator_name' => 'Oficial Vigilancia',
            'departure_time' => '08:30',
            'control_number' => 'CTRL-A4-01',
            'peripherals_json' => $mockPeriphs,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        // 2. Renderizar PDF con DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.inventory.pdf.movilizacion_equipos', [
            'assignment' => $assignment,
            'isPdf' => true,
        ]);
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        // Debe caber estrictamente en 1 página Carta (Letter)
        $this->assertEquals(1, $pdf->getDomPDF()->getCanvas()->get_page_count());

        // 3. Verificar en el HTML generado
        $html = view('admin.inventory.pdf.movilizacion_equipos', [
            'assignment' => $assignment,
            'isPdf' => true,
        ])->render();

        // NO debe contener "Asociado a ..."
        $this->assertStringNotContainsString('Asociado a', $html);
        // NO debe contener textos dummy de sin serial
        $this->assertStringNotContainsString('[Kit operativo - Sin serial]', $html);
        $this->assertStringNotContainsString('[S/N No reportado]', $html);
        // Debe contener el tipo de dispositivo y el serial real que sí existe
        $this->assertStringContainsString('MONITOR', $html);
        $this->assertStringContainsString('SN-PERIPH-01', $html);
    }

    public function test_assignment_sla_calculation_and_overdue_detection(): void
    {
        $site = MonitoredSite::first();
        $asset = InventoryAsset::create([
            'serial_number' => 'SLA-SN-' . uniqid(),
            'asset_tag' => 'AST-SLA-' . uniqid(),
            'name' => 'PC-SLA-TEST',
            'type' => 'desktop',
            'status' => 'assigned',
            'monitored_site_id' => $site->id,
        ]);

        // 1. Asignación reciente (< 15 días) sin digitalizar
        $recentAssignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-SLA-REC-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Funcionario Reciente',
            'movement_type' => 'assignment',
            'assigned_at' => now()->subDays(5),
            'status' => 'active',
        ]);

        $this->assertFalse($recentAssignment->has_signed_scan);
        $this->assertEquals(5, $recentAssignment->days_since_assigned);
        $this->assertEquals(10, $recentAssignment->days_remaining_for_scan);
        $this->assertEquals('pending', $recentAssignment->scan_compliance_status);

        // 2. Asignación vencida (> 15 días) sin digitalizar
        $overdueAssignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-SLA-OVR-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Funcionario En Mora',
            'movement_type' => 'assignment',
            'assigned_at' => now()->subDays(18),
            'status' => 'active',
        ]);

        $this->assertFalse($overdueAssignment->has_signed_scan);
        $this->assertEquals(18, $overdueAssignment->days_since_assigned);
        $this->assertEquals(0, $overdueAssignment->days_remaining_for_scan);
        $this->assertEquals('Digitalización Pendiente', $overdueAssignment->scan_compliance_label);
        $this->assertStringNotContainsString('SLA', $overdueAssignment->scan_compliance_label);

        // Scope overdue
        $this->assertTrue(InventoryAssignment::overdueScan()->where('id', $overdueAssignment->id)->exists());

        // 3. Asignación con documento digitalizado
        $overdueAssignment->document_pdf_path = 'assignments/scans/test.pdf';
        $overdueAssignment->save();

        $this->assertTrue($overdueAssignment->fresh()->has_signed_scan);
        $this->assertEquals('validated', $overdueAssignment->fresh()->scan_compliance_status);
        $this->assertEquals('Validada con Soporte Digital', $overdueAssignment->fresh()->scan_compliance_label);
    }

    public function test_asset_detects_outdated_assignment_when_peripheral_is_changed_after_assignment(): void
    {
        $site = MonitoredSite::first();
        $asset = InventoryAsset::create([
            'serial_number' => 'CHG-SN-' . uniqid(),
            'asset_tag' => 'AST-CHG-' . uniqid(),
            'name' => 'PC-CHG-TEST',
            'type' => 'desktop',
            'status' => 'assigned',
            'monitored_site_id' => $site->id,
        ]);

        // Periférico vinculado inicialmente al activo para coincidir con el acta
        \App\Models\InventoryPeripheral::create([
            'current_asset_id' => $asset->id,
            'type' => 'TECLADO',
            'serial_number' => 'KB-ORIGINAL-01',
            'monitored_site_id' => $site->id,
        ]);

        // Crear asignación activa hace 3 días
        $assignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-CHG-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Usuario Con Hardware Modificado',
            'movement_type' => 'assignment',
            'assigned_at' => now()->subDays(3),
            'created_at' => now()->subDays(3),
            'status' => 'active',
            'peripherals_json' => [
                ['type' => 'TECLADO', 'serial_number' => 'KB-ORIGINAL-01']
            ]
        ]);

        // Sin cambios posteriores: has_outdated_assignment es false
        $this->assertFalse($asset->fresh()->has_outdated_assignment);

        // Registrar un cambio de componente posterior al acta (now)
        \App\Models\InventoryComponentHistory::create([
            'inventory_asset_id' => $asset->id,
            'action' => 'replaced',
            'component_type' => 'peripheral',
            'old_value' => 'TECLADO (KB-ORIGINAL-01)',
            'new_value' => 'TECLADO (KB-NUEVO-99)',
            'details' => 'Sustitución de teclado por falla técnica',
            'recorded_at' => now(),
        ]);

        // Ahora has_outdated_assignment DEBE ser true
        $this->assertTrue($asset->fresh()->has_outdated_assignment);
    }

    public function test_creating_new_assignment_supersedes_previous_active_assignment_preserving_history(): void
    {
        $site = MonitoredSite::first();
        $asset = InventoryAsset::create([
            'serial_number' => 'SUP-SN-' . uniqid(),
            'asset_tag' => 'AST-SUP-' . uniqid(),
            'name' => 'PC-SUP-TEST',
            'type' => 'desktop',
            'status' => 'assigned',
            'monitored_site_id' => $site->id,
        ]);

        // 1. Primera asignación activa
        $firstAssignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-1-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Primer Funcionario',
            'movement_type' => 'assignment',
            'assigned_at' => now()->subDays(20),
            'status' => 'active',
        ]);

        // 2. Registrar nueva asignación a través de store
        $postData = [
            'movement_type' => 'assignment',
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Segundo Funcionario',
            'custodian_id_number' => 'V-99887766',
            'custodian_department' => 'Planificación',
            'provenance' => 'stock',
            'assigned_at' => now()->format('Y-m-d'),
            'observations' => 'Renovación por cambio de periféricos',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assignments.store'), $postData);

        $response->assertRedirect();

        // Verificar que la primera pasó a 'superseded' y sigue existiendo
        $firstAssignmentFresh = $firstAssignment->fresh();
        $this->assertEquals('superseded', $firstAssignmentFresh->status);

        // Verificar que la nueva está 'active'
        $secondAssignment = InventoryAssignment::where('custodian_name', 'Segundo Funcionario')->first();
        $this->assertNotNull($secondAssignment);
        $this->assertEquals('active', $secondAssignment->status);

        // Verificar que el censo histórico conserva ambas actas
        $this->assertEquals(2, InventoryAssignment::where('inventory_asset_id', $asset->id)->count());
    }

    public function test_can_upload_and_view_scanned_pdf_via_assignment_controller(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $site = MonitoredSite::first();
        $asset = InventoryAsset::create([
            'serial_number' => 'UPL-SN-' . uniqid(),
            'asset_tag' => 'AST-UPL-' . uniqid(),
            'name' => 'PC-UPL-TEST',
            'type' => 'desktop',
            'status' => 'assigned',
            'monitored_site_id' => $site->id,
        ]);

        $assignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-UPL-' . uniqid(),
            'inventory_asset_id' => $asset->id,
            'custodian_name' => 'Funcionario Carga',
            'movement_type' => 'assignment',
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $fakePdf = \Illuminate\Http\UploadedFile::fake()->create('acta_firmada.pdf', 150, 'application/pdf');

        $uploadResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.inventory.assignments.upload-scan', $assignment->id), [
                'scan_file' => $fakePdf,
                'observations' => 'Escaneo con firma del gerente y sello húmedo',
            ]);

        $uploadResponse->assertRedirect();
        $uploadResponse->assertSessionHas('success');

        $assignmentFresh = $assignment->fresh();
        $this->assertNotNull($assignmentFresh->document_pdf_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($assignmentFresh->document_pdf_path);

        // Probar visualización inline
        $viewResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.view-scan', $assignment->id));

        $viewResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $viewResponse->headers->get('content-type'));
    }

    public function test_assignments_index_shows_sla_column_and_no_banned_words(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.index'));

        $response->assertStatus(200);
        $response->assertSee('Soporte Digital');
        $response->assertDontSee('SLA', false);
        $response->assertDontSee('360°', false);
        $response->assertDontSee('Ficha 360', false);
        $response->assertDontSee('Puesto 360', false);
        $response->assertDontSee('custodio', false);
        $response->assertDontSee('Custodio', false);
    }
}

