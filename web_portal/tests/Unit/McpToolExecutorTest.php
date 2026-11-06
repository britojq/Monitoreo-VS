<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ToolExecutorInterface;
use Tests\TestCase;

class McpToolExecutorTest extends TestCase
{
    protected ToolExecutorInterface $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = app(ToolExecutorInterface::class);
    }

    public function test_get_ups_telemetry_returns_valid_data(): void
    {
        $res = $this->executor->getUpsTelemetry();

        $this->assertTrue($res['found']);
        $this->assertEquals('UPS-DEMO-01', $res['ups_id']);
        $this->assertStringContainsString('LV6KL', $res['model']);
        $this->assertArrayHasKey('input_voltage_vac', $res);
        $this->assertArrayHasKey('battery_percent', $res);
        $this->assertArrayHasKey('mode', $res);
        $this->assertIsFloat($res['input_voltage_vac']);
        $this->assertIsInt($res['battery_percent']);
    }

    public function test_get_ups_telemetry_handles_unknown_id(): void
    {
        $res = $this->executor->getUpsTelemetry('NON_EXISTENT_UPS_999');

        $this->assertFalse($res['found']);
        $this->assertArrayHasKey('error', $res);
    }

    public function test_search_itam_asset_by_ip_and_serial(): void
    {
        // 1. Búsqueda por IP de estación reclasificada
        $resIp = $this->executor->searchItamAsset('192.168.1.232');
        $this->assertTrue($resIp['found']);
        $this->assertGreaterThanOrEqual(1, $resIp['total_matches']);
        $this->assertEquals('192.168.1.232', $resIp['assets'][0]['ip']);

        // 2. Búsqueda por serial con acta escaneada
        $resSerial = $this->executor->searchItamAsset('MXL52434KR');
        $this->assertTrue($resSerial['found']);
        $this->assertEquals('MXL52434KR', $resSerial['assets'][0]['serial']);
        $this->assertNotNull($resSerial['assets'][0]['acta_oficial']);
        $this->assertEquals('UINF-4.05-2026-0014', $resSerial['assets'][0]['acta_oficial']['codigo_acta']);
        $this->assertTrue($resSerial['assets'][0]['acta_oficial']['tiene_pdf_firmado']);
    }

    public function test_search_itam_asset_handles_not_found(): void
    {
        $res = $this->executor->searchItamAsset('SERIAL_INVENTADO_NO_EXISTE');

        $this->assertFalse($res['found']);
        $this->assertArrayHasKey('message', $res);
    }

    public function test_get_noc_site_status_all_and_filtered(): void
    {
        // 1. Todas las sedes
        $resAll = $this->executor->getNocSiteStatus();
        $this->assertTrue($resAll['found']);
        $this->assertGreaterThanOrEqual(5, $resAll['total_sedes']);
        $this->assertArrayHasKey('sedes_online', $resAll);

        // 2. Sede filtrada por nombre
        $resFiltered = $this->executor->getNocSiteStatus('Valle Seco');
        $this->assertTrue($resFiltered['found']);
        $this->assertGreaterThanOrEqual(1, $resFiltered['total_sedes']);
        $this->assertStringContainsStringIgnoringCase('Valle Seco', $resFiltered['sedes'][0]['nombre']);
    }

    public function test_get_proxy_status_returns_proxies(): void
    {
        $res = $this->executor->getProxyStatus();

        $this->assertTrue($res['found']);
        $this->assertArrayHasKey('total_proxies', $res);
        $this->assertArrayHasKey('proxies', $res);
    }

    public function test_get_inventory_summary_returns_aggregated_data(): void
    {
        $res = $this->executor->getInventorySummary();

        $this->assertTrue($res['found']);
        $this->assertArrayHasKey('total_equipos', $res);
        $this->assertArrayHasKey('desglose_por_tipo', $res);
        $this->assertGreaterThan(0, $res['total_equipos']);
        $this->assertArrayHasKey('equipos_con_serial_auditado', $res);
        $this->assertArrayHasKey('equipos_con_acta_fisica_firmada', $res);
    }

    public function test_execute_tool_dispatching_and_unknown_tool(): void
    {
        // Despacho válido
        $resValid = $this->executor->executeTool('get_ups_telemetry', ['ups_id' => 'UPS-DEMO-01']);
        $this->assertTrue($resValid['found']);
        $this->assertEquals('UPS-DEMO-01', $resValid['ups_id']);

        // Despacho de herramienta desconocida (defensa contra alucinaciones)
        $resInvalid = $this->executor->executeTool('delete_all_servers', ['confirm' => true]);
        $this->assertFalse($resInvalid['found']);
        $this->assertStringContainsString('no está autorizada', $resInvalid['error']);
    }
}
