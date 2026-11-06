<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Snmp\CreateSnmpDeviceAction;
use App\Jobs\ActivateRemoteSnmpJob;
use App\Jobs\SnmpDiscoveryJob;
use App\Models\SnmpDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase3AsyncQueueAndJobsV55330Test extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);

        if (!$this->adminUser->terms_accepted_at) {
            $this->adminUser->update(['terms_accepted_at' => now()]);
        }
    }

    /**
     * ARC-06: Verificar que CreateSnmpDeviceAction despacha SnmpDiscoveryJob
     * de forma asíncrona erradicando el uso residual de exec().
     */
    public function test_create_snmp_device_dispatches_discovery_job(): void
    {
        Queue::fake();

        $action = $this->app->make(CreateSnmpDeviceAction::class);
        $result = $action->execute([
            'name' => 'Switch Core Edificio Principal',
            'ip_address' => '192.168.1.240',
            'snmp_version' => 'v2c',
            'community' => 'SafeCommRO',
            'device_type' => 'switch',
            'site_id' => null,
            'poll_interval_seconds' => 60,
            'notes' => 'Dispositivo de prueba ARC-06',
        ], '192.168.1.50', 'TestAuditAgent/3.0');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(SnmpDevice::class, $result['device']);

        // Verificar que el Job formal fue encolado con modo 'interfaces'
        Queue::assertPushed(SnmpDiscoveryJob::class, function (SnmpDiscoveryJob $job) use ($result) {
            return $job->deviceId === $result['device']->id && $job->mode === 'interfaces';
        });

        // Verificar registro de auditoría en MariaDB SSOT
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $result['device']->id,
        ]);
    }

    /**
     * ARC-06: Verificar que la activación remota SNMP soporta desacoplamiento
     * asíncrono despachando ActivateRemoteSnmpJob con HTTP 202 Accepted.
     */
    public function test_activate_remote_snmp_async_dispatches_job_and_returns_202(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.snmp.activate'), [
            'ip_address' => '192.168.1.241',
            'method' => 'ssh',
            'username' => 'admin_cisco',
            'password' => 'SafePassword#123',
            'secret' => 'SafeEnable#456',
            'community' => 'SafeCommRO',
            'port' => 22,
            'async' => true,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'status' => 'queued',
        ]);

        Queue::assertPushed(ActivateRemoteSnmpJob::class, function (ActivateRemoteSnmpJob $job) {
            return ($job->validated['ip_address'] ?? '') === '192.168.1.241'
                && ($job->validated['method'] ?? '') === 'ssh';
        });
    }

    /**
     * ARC-08: Verificar que la plantilla del servicio systemd sonar-queue-worker
     * existe y apunta a un binario PHP válido para procesamiento en tiempo real (<500ms).
     */
    public function test_sonar_queue_worker_service_template_is_valid(): void
    {
        $templatePath = base_path('../system/templates/systemd/sonar-queue-worker.service');
        $this->assertFileExists($templatePath);

        $content = file_get_contents($templatePath);
        $this->assertStringContainsString('ExecStart=/usr/bin/php', $content);
        $this->assertStringContainsString('artisan queue:work --sleep=1 --tries=3', $content);
        $this->assertStringContainsString('User=www-data', $content);
        $this->assertTrue(is_executable('/usr/bin/php'));
    }
}
