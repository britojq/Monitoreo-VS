<?php

namespace Tests\Feature;

use App\Contracts\ExecutiveReportInterface;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExecutiveReportsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $operatorUser;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_reports_test@valle-seco.local'],
            [
                'name' => 'Admin Reportes Tester',
                'role' => 'admin',
                'is_active' => true,
                'terms_accepted' => true,
                'password' => bcrypt('password123'),
            ]
        );
        $this->adminUser->update(['is_active' => true]);

        $this->operatorUser = User::firstOrCreate(
            ['email' => 'operator_reports_test@valle-seco.local'],
            [
                'name' => 'Operador Reportes Tester',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => ['reports.view', 'reports.export'],
                'password' => bcrypt('password123'),
            ]
        );
        $this->operatorUser->update(['is_active' => true]);

        $this->unauthorizedUser = User::firstOrCreate(
            ['email' => 'unauth_reports_test@valle-seco.local'],
            [
                'name' => 'Usuario No Autorizado Reportes',
                'role' => 'operator',
                'is_active' => true,
                'terms_accepted' => true,
                'permissions' => [], // Sin permisos
                'password' => bcrypt('password123'),
            ]
        );
        $this->unauthorizedUser->update(['is_active' => true]);
    }

    public function test_admin_and_authorized_operator_can_access_reports_center(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.index');
        $response->assertSee('Centro de Reportes &amp; Exportación NOC + ITAM', false);
        $response->assertSee('Censo Maestro NOC + ITAM');
        $response->assertSee('Cumplimiento de Actas UINF-4.05');
        $response->assertSee('Disponibilidad de Red &amp; Servicios NOC', false);
        $response->assertDontSee('Catálogo de Direccionamiento IPAM');

        $response->assertSee('Exportación Segmentada');
        $response->assertSee('Inventario Unificado');
        $response->assertSee('Asignaciones de Equipos, Movilizaciones');
        $response->assertDontSee('Libro Completo');
        $response->assertDontSee('Exportación Segmentada por Formato Institucional');
        $response->assertDontSee('Asignaciones de Equipos, Movilizaciones y Control SLA');
        $response->assertDontSee('plazo de 15 días continuos');
        $response->assertDontSee('alertas de mora');
        $response->assertDontSee('(15d)');

        // Verificación estricta del archivo de la vista admin/reports/index.blade.php
        $reportsViewBlade = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $this->assertStringNotContainsStringIgnoringCase('institucional', $reportsViewBlade);
        $this->assertStringNotContainsStringIgnoringCase('libro completo', $reportsViewBlade);
        $this->assertStringNotContainsStringIgnoringCase('ejecutivo', $reportsViewBlade);
        $this->assertStringNotContainsStringIgnoringCase('oficial', $reportsViewBlade);

        // Verificación estricta de erradicación global en la página
        $content = strtolower($response->getContent());
        $this->assertStringNotContainsString('ejecutivo', $content);
        $this->assertStringNotContainsString('ejecutiva', $content);
        $this->assertStringNotContainsString('oficial', $content);
        $this->assertStringNotContainsString('360°', $content);
        $this->assertStringNotContainsString('custodio', $content);
        $this->assertStringNotContainsString('ollama', $content);

        // Acceso del operador autorizado
        $respOperator = $this->actingAs($this->operatorUser)->get(route('admin.reports.index'));
        $respOperator->assertStatus(200);
    }

    public function test_unauthorized_user_receives_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.reports.index'));
        $response->assertStatus(403);
    }

    public function test_export_census_csv_streams_file_and_logs_audit(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'census', 'format' => 'csv']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('reporte_census_', $response->headers->get('content-disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'reports',
            'event' => 'exported',
        ]);
    }

    public function test_export_census_csv_categories(): void
    {
        // 1. Servidores
        $respServers = $this->actingAs($this->adminUser)->get(route('admin.reports.export', [
            'type' => 'census',
            'format' => 'csv',
            'category' => 'servers',
        ]));
        $respServers->assertStatus(200);
        $this->assertStringContainsString('inventario_servidores_', $respServers->headers->get('content-disposition'));

        // 2. Telecomunicaciones
        $respTelecom = $this->actingAs($this->adminUser)->get(route('admin.reports.export', [
            'type' => 'census',
            'format' => 'csv',
            'category' => 'telecom',
        ]));
        $respTelecom->assertStatus(200);
        $this->assertStringContainsString('inventario_telecomunicaciones_', $respTelecom->headers->get('content-disposition'));

        // 3. Equipos de Computación
        $respWorkstations = $this->actingAs($this->adminUser)->get(route('admin.reports.export', [
            'type' => 'census',
            'format' => 'csv',
            'category' => 'workstations',
        ]));
        $respWorkstations->assertStatus(200);
        $this->assertStringContainsString('inventario_equipos_computacion_', $respWorkstations->headers->get('content-disposition'));
    }

    public function test_export_invalid_type_falls_back_to_census(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'unknown_type', 'format' => 'csv']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('reporte_census_', $response->headers->get('content-disposition'));
    }

    public function test_export_compliance_csv_streams_file(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'compliance', 'format' => 'csv']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('reporte_compliance_', $response->headers->get('content-disposition'));
    }

    public function test_export_lifecycle_csv_streams_file(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'lifecycle', 'format' => 'csv']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('reporte_lifecycle_', $response->headers->get('content-disposition'));
    }

    public function test_export_sla_csv_streams_file(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'sla', 'format' => 'csv']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('reporte_sla_', $response->headers->get('content-disposition'));
    }

    public function test_export_pdf_returns_download_and_logs_audit(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'census', 'format' => 'pdf']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('reporte_census_', $response->headers->get('content-disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'reports',
            'event' => 'exported',
        ]);
    }

    public function test_export_excel_returns_spreadsheet_with_two_sheets_and_logs_audit(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.reports.export', ['type' => 'census', 'format' => 'excel']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/vnd.ms-excel', $response->headers->get('content-type'));
        $this->assertStringContainsString('Inventario_Equipos_Tecnologicos_', $response->headers->get('content-disposition'));

        $content = $response->getContent();
        // Verificar que contiene las 2 hojas exactas requeridas
        $this->assertStringContainsString('ss:Name="Centro de Datos"', $content);
        $this->assertStringContainsString('ss:Name="Estaciones de Trabajo"', $content);
        $this->assertStringContainsString('Gerencia General de ATIT', $content);
        $this->assertStringContainsString('ASIGNADO', $content);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'reports',
            'event' => 'exported',
        ]);
    }

    public function test_export_excel_by_individual_categories(): void
    {
        // 1. Categoría Servidores
        $respServers = $this->actingAs($this->adminUser)->get(route('admin.reports.export', [
            'type' => 'census',
            'format' => 'excel',
            'category' => 'servers',
        ]));
        $respServers->assertStatus(200);
        $this->assertStringContainsString('Inventario_Servidores_', $respServers->headers->get('content-disposition'));
        $contentServers = $respServers->getContent();
        $this->assertStringContainsString('ss:Name="Servidores"', $contentServers);
        $this->assertStringContainsString('SERVIDORES Y CENTRO DE DATOS', $contentServers);

        // 2. Categoría Telecomunicaciones
        $respTelecom = $this->actingAs($this->adminUser)->get(route('admin.reports.export', [
            'type' => 'census',
            'format' => 'excel',
            'category' => 'telecom',
        ]));
        $respTelecom->assertStatus(200);
        $this->assertStringContainsString('Inventario_Telecomunicaciones_', $respTelecom->headers->get('content-disposition'));
        $contentTelecom = $respTelecom->getContent();
        $this->assertStringContainsString('ss:Name="Telecomunicaciones"', $contentTelecom);
        $this->assertStringContainsString('TELECOMUNICACIONES Y DISPOSITIVOS DE RED', $contentTelecom);

        // 3. Categoría Equipos de Computación
        $respWorkstations = $this->actingAs($this->adminUser)->get(route('admin.reports.export', [
            'type' => 'census',
            'format' => 'excel',
            'category' => 'workstations',
        ]));
        $respWorkstations->assertStatus(200);
        $this->assertStringContainsString('Inventario_Equipos_Computacion_', $respWorkstations->headers->get('content-disposition'));
        $contentWorkstations = $respWorkstations->getContent();
        $this->assertStringContainsString('ss:Name="Estaciones de Trabajo"', $contentWorkstations);
        $this->assertStringContainsString('ASIGNADO', $contentWorkstations);
    }
}
