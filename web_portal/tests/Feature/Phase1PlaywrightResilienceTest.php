<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\System\SonarPathService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class Phase1PlaywrightResilienceTest extends TestCase
{
    /**
     * ARC-09 / SOLID: Verificar que ScreenshotConfigDTO en Python resuelva
     * correctamente ignore_https_errors=True para tolerar certificados SSL locales de intranet.
     */
    public function test_screenshot_config_dto_resolves_ssl_bypass(): void
    {
        $pythonExec = SonarPathService::pythonBinary();
        $code = "import sys; sys.path.insert(0, '/opt/sonar'); " .
                "from monitor.domain.dto.screenshot_config_dto import ScreenshotConfigDTO; " .
                "cfg = ScreenshotConfigDTO.create_default(); " .
                "print(f'{cfg.ignore_https_errors}|{cfg.viewport_width}|{cfg.base_url}')";

        $res = Process::timeout(10)->run([$pythonExec, '-c', $code]);

        $this->assertSame(0, $res->exitCode(), 'Error ejecutando inspección de ScreenshotConfigDTO: ' . $res->errorOutput());
        $output = trim($res->output());
        $this->assertStringStartsWith('True|1920|', $output);
    }

    /**
     * SOLID & Resiliencia SSL: Verificar que la captura de servicios se genere exitosamente
     * en PNG con tamaño superior a 10KB a través del adaptador PlaywrightScreenshotClient.
     */
    public function test_playwright_client_captures_servicios_screenshot_over_https(): void
    {
        $pythonExec = SonarPathService::pythonBinary();
        $code = "import asyncio, sys; sys.path.insert(0, '/opt/sonar'); " .
                "from monitor.tools.web_screenshot import capture_web_dashboard; " .
                "p = asyncio.run(capture_web_dashboard('servicios', timeout=15.0)); " .
                "print(str(p) if p else '')";

        $res = Process::timeout(30)->run([$pythonExec, '-c', $code]);

        $this->assertSame(0, $res->exitCode(), 'Error en proceso de captura: ' . $res->errorOutput());
        $path = trim($res->output());

        $this->assertNotEmpty($path, 'La ruta generada no debe estar vacía.');
        $this->assertFileExists($path, "El archivo de captura no existe en disco: {$path}");
        $this->assertGreaterThan(10000, filesize($path), 'La imagen capturada debe superar los 10KB.');
    }

    /**
     * SOLID & Resiliencia SSL: Verificar que la captura de sedes regionales se genere
     * exitosamente en PNG con tamaño superior a 10KB expandiendo los acordeones en el DOM.
     */
    public function test_playwright_client_captures_sedes_screenshot_over_https(): void
    {
        $pythonExec = SonarPathService::pythonBinary();
        $code = "import asyncio, sys; sys.path.insert(0, '/opt/sonar'); " .
                "from monitor.tools.web_screenshot import capture_web_dashboard; " .
                "p = asyncio.run(capture_web_dashboard('sedes', timeout=15.0)); " .
                "print(str(p) if p else '')";

        $res = Process::timeout(30)->run([$pythonExec, '-c', $code]);

        $this->assertSame(0, $res->exitCode(), 'Error en proceso de captura de sedes: ' . $res->errorOutput());
        $path = trim($res->output());

        $this->assertNotEmpty($path, 'La ruta generada para sedes no debe estar vacía.');
        $this->assertFileExists($path, "El archivo de captura de sedes no existe: {$path}");
        $this->assertGreaterThan(10000, filesize($path), 'La imagen de sedes debe superar los 10KB.');
    }

    /**
     * Garantía de Inmutabilidad Criptográfica: Verificar que el archivo de auditoría
     * docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md se mantenga intacto.
     */
    public function test_audit_report_hash_remains_strictly_immutable(): void
    {
        $auditReport = base_path('../docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md');
        $this->assertFileExists($auditReport);

        $expectedHash = '88d8174095afbe98c7ad47e6c9cd86e9bc5c1d3c6690d13de9d52fff9f6cd165';
        $actualHash = hash_file('sha256', $auditReport);

        $this->assertSame(
            $expectedHash,
            $actualHash,
            'CRÍTICO: El archivo de auditoría ha sido alterado o manipulado.'
        );
    }
}
