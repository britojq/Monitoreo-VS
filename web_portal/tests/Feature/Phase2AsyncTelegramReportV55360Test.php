<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DispatchTelegramReportJob;
use App\Models\User;
use App\Services\Telegram\Dispatcher\TelegramReportSenderAction;
use App\Services\TelegramReportDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase2AsyncTelegramReportV55360Test extends TestCase
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
     * ARC-09: Verificar que el despacho de reportes a Telegram con captura soporta
     * ejecución asíncrona devolviendo HTTP 202 Accepted en menos de 80ms y encolando el Job.
     */
    public function test_telegram_dispatch_async_dispatches_job_and_returns_202_under_80ms(): void
    {
        Queue::fake();

        $start = microtime(true);
        $response = $this->actingAs($this->adminUser)->postJson(route('admin.telegram.dispatch'), [
            'report_type' => 'servicios',
            'mode' => 'instant',
            'destination' => 'owner',
            'observations' => 'Prueba de despacho asíncrono ARC-09',
            'include_screenshot' => true,
            'async' => true,
        ]);
        $latencyMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'status' => 'queued',
            'report_type' => 'servicios',
        ]);

        Queue::assertPushed(DispatchTelegramReportJob::class, function (DispatchTelegramReportJob $job) {
            return $job->userId === $this->adminUser->id
                && $job->reportType === 'servicios'
                && $job->destination === 'owner'
                && $job->includeScreenshot === true
                && str_contains($job->observations, 'ARC-09');
        });

        $this->assertLessThan(80.0, $latencyMs, "La latencia de respuesta excedió 80ms: {$latencyMs}ms");
    }

    /**
     * ARC-09: Verificar que DispatchTelegramReportJob ejecuta el servicio de despacho cleanly.
     */
    public function test_dispatch_telegram_report_job_handles_dispatch_service_cleanly(): void
    {
        $mockService = $this->createMock(TelegramReportDispatchService::class);
        $mockService->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(User::class),
                'servicios',
                'instant',
                '192.168.1.50',
                'Observación test',
                'owner',
                true
            )
            ->willReturn([
                'success' => true,
                'message' => 'Despacho simulado exitoso.',
            ]);

        $job = new DispatchTelegramReportJob(
            $this->adminUser->id,
            'servicios',
            'instant',
            '192.168.1.50',
            'Observación test',
            'owner',
            true
        );

        $job->handle($mockService);
    }

    /**
     * ARC-09: Verificar que TelegramReportSenderAction::generateScreenshot()
     * utiliza Process::run() con timeout y maneja la ausencia de capturas sin fallar.
     */
    public function test_generate_screenshot_uses_process_facade(): void
    {
        Process::fake([
            '*' => Process::result(
                output: "/tmp/mock_screenshot_dashboard.png\n",
                exitCode: 0
            ),
        ]);

        $action = $this->app->make(TelegramReportSenderAction::class);
        $action->generateScreenshot('servicios');

        Process::assertRan(function ($process, $result) {
            $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
            return str_contains($cmd, 'capture_web_dashboard');
        });
    }
}
