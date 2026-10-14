<?php

namespace Tests\Feature;

use App\Contracts\ActaVerificationInterface;
use App\Contracts\QrCodeGeneratorInterface;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ActaQrVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $techUser;
    protected InventoryAsset $testAsset;
    protected InventoryAssignment $testAssignment;
    protected MonitoredSite $testSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_acta_test@valle-seco.local'],
            [
                'name' => 'Administrador Acta Tester',
                'role' => 'admin',
                'is_active' => true,
                'terms_accepted' => true,
                'password' => bcrypt('password123'),
            ]
        );
        $this->adminUser->update(['is_active' => true]);

        $this->techUser = User::firstOrCreate(
            ['email' => 'tech_acta_test@valle-seco.local'],
            [
                'name' => 'Técnico Informática Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => ['inventory.view', 'inventory.assign'],
                'password' => bcrypt('password123'),
            ]
        );
        $this->techUser->update(['is_active' => true]);

        $this->testSite = MonitoredSite::firstOrCreate(
            ['name' => 'SEDE CENTRAL VALLE SECO'],
            [
                'ip' => '10.20.23.1',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $this->testAsset = InventoryAsset::create([
            'asset_tag' => 'AST-QR-' . uniqid(),
            'name' => 'ESTACIÓN TRABAJO TAQUILLA 01',
            'type' => 'desktop',
            'status' => 'assigned',
            'ip_address' => '10.20.23.210',
            'mac_address' => '00:1a:2b:3c:4d:ef',
            'serial_number' => 'SN-QR-' . uniqid(),
            'brand' => 'HP',
            'model' => 'ProDesk 600 G3',
            'monitored_site_id' => $this->testSite->id,
        ]);

        $this->testAssignment = InventoryAssignment::create([
            'assignment_code' => 'UINF-4.05-' . strtoupper(substr(uniqid(), -6)),
            'control_number' => 'CTRL-' . rand(1000, 9999),
            'inventory_asset_id' => $this->testAsset->id,
            'user_id' => $this->adminUser->id,
            'delivered_by_user_id' => $this->techUser->id,
            'monitored_site_id' => $this->testSite->id,
            'movement_type' => 'assignment',
            'status' => 'active',
            'custodian_name' => 'Carlos Alberto Morales',
            'custodian_id_number' => 'V-14.889.321',
            'custodian_department' => 'Oficina Comercial Valle Seco',
            'checkpoint_name' => 'Garita Principal Valle Seco',
            'assigned_at' => now(),
            'hardware_specs' => [
                'processor' => 'Intel Core i5-7500 @ 3.40GHz',
                'ram' => '16 GB DDR4',
                'disk' => '512 GB SSD NVMe',
                'os' => 'Debian GNU/Linux 12 (Bookworm)',
                'device_name' => 'CENCARCOM012010',
                'ip_address' => '10.20.23.210',
            ],
            'peripherals_json' => [
                ['type' => 'MONITOR', 'brand' => 'HP', 'model' => 'V214a', 'serial_number' => '6CM8191234', 'observations' => 'Operativo'],
                ['type' => 'TECLADO', 'brand' => 'Genius', 'model' => 'KB-110', 'serial_number' => 'KB-99182', 'observations' => 'USB'],
            ],
        ]);
    }

    public function test_qr_code_generator_service_creates_svg_and_data_uri(): void
    {
        /** @var QrCodeGeneratorInterface $qrService */
        $qrService = app(QrCodeGeneratorInterface::class);

        $testUrl = 'https://monitoreo.valle-seco.local/acta/verificar/UINF-4.05-TEST';
        $svg = $qrService->generateSvg($testUrl, 150);
        $dataUri = $qrService->generateDataUri($testUrl, 150);

        $this->assertNotEmpty($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);

        $this->assertNotEmpty($dataUri);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }

    public function test_acta_verification_service_generates_and_validates_signature(): void
    {
        /** @var ActaVerificationInterface $service */
        $service = app(ActaVerificationInterface::class);

        $signature = $service->generateSignature($this->testAssignment);
        $url = $service->getVerificationUrl($this->testAssignment);

        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature)); // SHA-256 hex string

        $this->assertNotEmpty($url);
        $this->assertStringContainsString($this->testAssignment->assignment_code, $url);
        $this->assertStringContainsString('sig=', $url);

        // Validar firma auténtica
        $this->assertTrue($service->verifySignature($this->testAssignment, $signature));
        $this->assertTrue($service->verifySignature($this->testAssignment, substr($signature, 0, 16)));

        // Rechazar firma fraudulenta
        $this->assertFalse($service->verifySignature($this->testAssignment, 'firma_invalida_falsa_1234567890'));
    }

    public function test_public_verification_view_displays_authentic_document_and_banned_terms_are_absent(): void
    {
        /** @var ActaVerificationInterface $service */
        $service = app(ActaVerificationInterface::class);
        $signature = $service->generateSignature($this->testAssignment);
        $sigShort = substr($signature, 0, 16);

        $response = $this->get(route('public.acta.verify', [
            'code' => $this->testAssignment->assignment_code,
            'sig' => $sigShort,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('public.acta_verify');
        $response->assertSee($this->testAssignment->assignment_code);
        $response->assertSee('DOCUMENTO AUTÉNTICO');
        $response->assertSee(strtoupper($this->testAssignment->custodian_name));
        $response->assertSee('ESTACIÓN TRABAJO TAQUILLA 01'); // Nombre Etiqueta (SSOT Regla #7)
        $response->assertSee('HP ProDesk 600 G3');

        // Cumplimiento estricto de Regla de Oro #9 y Error #7
        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
        $response->assertDontSee('SLOT #');
        $response->assertDontSee('Ollama');
    }

    public function test_public_verification_view_handles_unknown_acta_gracefully(): void
    {
        $response = $this->get(route('public.acta.verify', [
            'code' => 'UINF-4.05-NOEXISTE-999',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('public.acta_not_found');
        $response->assertSee('Acta No Registrada');
        $response->assertSee('UINF-4.05-NOEXISTE-999');
    }

    public function test_assignment_show_view_includes_qr_code_and_verification_url(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.show', $this->testAssignment->id));

        $response->assertStatus(200);
        $response->assertSee('Sello Digital');
        $response->assertSee('Validar Certificado');
        $response->assertSee('data:image/svg+xml;base64,', false);
        $response->assertSee(route('public.acta.verify', $this->testAssignment->assignment_code));

        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
    }

    public function test_assignment_pdf_acta_view_renders_with_embedded_qr_code(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.pdf', $this->testAssignment->id));

        $response->assertStatus(200);
        $response->assertSee('VALIDACIÓN QR');
        $response->assertSee('data:image/svg+xml;base64,');
        $response->assertSee('UINF-4.05');

        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
    }

    public function test_assignment_pdf_movilizacion_view_renders_with_checkpoint_qr(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.inventory.assignments.pdf-movilizacion', $this->testAssignment->id));

        $response->assertStatus(200);
        $response->assertSee('CONTROL PCP - VERIFICACIÓN');
        $response->assertSee('data:image/svg+xml;base64,');
        $response->assertSee('CONSTANCIA DE ENTRADA Y SALIDA');

        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
    }
}
