<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DispatchTelegramReportJob;
use App\Models\TelegramDispatch;
use App\Models\User;
use App\Services\Telegram\Dispatcher\TelegramReportSenderAction;
use App\Services\TelegramReportDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase3TelegramReportWithScreenshotTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->adminUser->update([
            'name' => 'Ing. Jose Brito Test',
            'cedula' => 'V-18765432',
            'personal_number' => 'ATIT-9922',
            'phone' => '+584120001122',
            'terms_accepted_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Micro-Paso 3.1: Verificar que generateScreenshot('servicios') genera físicamente
     * un archivo PNG válido en disco, mayor a 10KB y con la cabecera mágica de imagen PNG.
     */
    public function test_generate_screenshot_servicios_produces_real_valid_png(): void
    {
        /** @var TelegramReportSenderAction $action */
        $action = $this->app->make(TelegramReportSenderAction::class);
        $path = $action->generateScreenshot('servicios');

        $this->assertNotNull($path, 'La ruta de la captura generada no debe ser nula');
        $this->assertFileExists($path, 'El archivo de captura física debe existir en disco');

        $fileSize = filesize($path);
        $this->assertGreaterThan(10240, $fileSize, "El tamaño del archivo ({$fileSize} bytes) debe superar 10KB");

        // Validar número mágico de PNG: 89 50 4E 47 0D 0A 1A 0A
        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        $magicBytes = fread($handle, 8);
        fclose($handle);

        $this->assertSame("\x89PNG\r\n\x1a\n", $magicBytes, 'El archivo generado debe poseer la firma canónica PNG');
    }

    /**
     * Micro-Paso 3.1: Verificar que generateScreenshot('sedes') genera físicamente
     * una captura PNG válida de sedes y enlaces regionales.
     */
    public function test_generate_screenshot_sedes_produces_real_valid_png(): void
    {
        /** @var TelegramReportSenderAction $action */
        $action = $this->app->make(TelegramReportSenderAction::class);
        $path = $action->generateScreenshot('sedes');

        $this->assertNotNull($path, 'La ruta de la captura de sedes no debe ser nula');
        $this->assertFileExists($path, 'El archivo de captura física de sedes debe existir en disco');

        $fileSize = filesize($path);
        $this->assertGreaterThan(10240, $fileSize, "El tamaño del archivo ({$fileSize} bytes) debe superar 10KB");

        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        $magicBytes = fread($handle, 8);
        fclose($handle);

        $this->assertSame("\x89PNG\r\n\x1a\n", $magicBytes, 'El archivo de sedes debe ser un PNG auténtico');
    }

    /**
     * Micro-Paso 3.1: Verificar que TelegramReportSenderAction::execute() con includeScreenshot=true
     * despacha tanto el mensaje textual como la fotografía con caption HTML hacia Telegram (faked).
     */
    public function test_telegram_sender_action_executes_with_screenshot_and_dispatches_photo(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 12345]], 200),
        ]);

        /** @var TelegramReportSenderAction $action */
        $action = $this->app->make(TelegramReportSenderAction::class);

        $result = $action->execute(
            user: $this->adminUser,
            reportType: 'servicios',
            mode: 'instant',
            ipAddress: '192.168.1.220',
            observations: 'Prueba E2E de despacho con captura Playwright v5.54.80',
            destination: 'owner',
            includeScreenshot: true,
            reportText: '<b>REPORTE TÉCNICO OFICIAL DE SERVICIOS</b>'
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['has_screenshot']);
        $this->assertInstanceOf(TelegramDispatch::class, $result['dispatch']);
        $this->assertSame('success', $result['dispatch']->status);

        // Verificar que sendMessage fue llamado
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '38914901'
                && str_contains($request['text'], 'REPORTE TÉCNICO OFICIAL DE SERVICIOS');
        });

        // Verificar que sendPhoto fue llamado con multipart y el caption corporativo
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'sendPhoto')
                && str_contains($request->body(), 'SISTEMA DE MONITOREO VALLE SECO');
        });
    }

    /**
     * Micro-Paso 3.2: Verificar que DispatchTelegramReportJob procesa un despacho
     * completo de forma autónoma con captura adjunta usando TelegramReportDispatchService.
     */
    public function test_dispatch_telegram_report_job_handles_real_service_with_screenshot(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 54321]], 200),
        ]);

        $job = new DispatchTelegramReportJob(
            userId: $this->adminUser->id,
            reportType: 'servicios',
            mode: 'instant',
            ipAddress: '192.168.1.220',
            observations: 'Despacho asíncrono con captura Job test',
            destination: 'owner',
            includeScreenshot: true
        );

        /** @var TelegramReportDispatchService $service */
        $service = $this->app->make(TelegramReportDispatchService::class);
        $job->handle($service);

        // Validar que se registró el despacho en la base de datos
        $this->assertDatabaseHas('telegram_dispatches', [
            'user_id' => $this->adminUser->id,
            'report_type' => 'servicios',
            'status' => 'success',
        ]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'sendPhoto');
        });
    }

    /**
     * Regla de Oro #1: Prohibición Absoluta de Despachos a Grupos de Telegram.
     * En entorno de pruebas automatizadas, si el destino seleccionado es 'group',
     * el sistema debe descartar chats de grupo (-100...) protegiendo el canal corporativo.
     */
    public function test_rule_1_group_safety_filters_out_negative_chat_ids_during_tests(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        /** @var TelegramReportSenderAction $action */
        $action = $this->app->make(TelegramReportSenderAction::class);

        // Destino 'group' puro en tests debe filtrar los chat_ids negativos
        $result = $action->execute(
            user: $this->adminUser,
            reportType: 'servicios',
            mode: 'instant',
            ipAddress: '192.168.1.220',
            observations: 'Prueba de salvaguarda Regla #1',
            destination: 'group',
            includeScreenshot: false,
            reportText: 'Test de aislamiento'
        );

        // No debe haber enviado ninguna petición con chat_id negativo
        Http::assertNotSent(function (Request $request) {
            return isset($request['chat_id']) && str_starts_with((string)$request['chat_id'], '-');
        });

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No se definieron destinatarios válidos', $result['message']);
    }
}
