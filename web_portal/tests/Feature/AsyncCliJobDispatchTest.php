<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AnalyzeNetworkPredictiveJob;
use App\Jobs\SnmpDiscoveryJob;
use App\Jobs\TriggerTelemetrySyncJob;
use App\Jobs\WakeOnLanJob;
use App\Models\SnmpDevice;
use App\Models\User;
use App\Models\WolDevice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncCliJobDispatchTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected WolDevice $wolDevice;
    protected SnmpDevice $snmpDevice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);

        $this->wolDevice = WolDevice::first() ?? WolDevice::create([
            'name' => 'PC Test WOL ARC-02',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'ip_address' => '192.168.1.199',
            'broadcast_address' => '192.168.1.255',
            'port' => 9,
            'is_active' => true,
        ]);

        $this->snmpDevice = SnmpDevice::first() ?? SnmpDevice::create([
            'name' => 'Switch Test SNMP ARC-02',
            'ip_address' => '192.168.1.198',
            'community' => 'public',
            'snmp_version' => 'v2c',
            'port' => 161,
            'is_active' => true,
        ]);
    }

    public function test_wol_wake_dispatches_job_and_returns_202_under_80ms(): void
    {
        Queue::fake();

        $start = microtime(true);
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.wol.wake', $this->wolDevice->id));
        $latencyMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'status' => 'queued',
        ]);

        Queue::assertPushed(WakeOnLanJob::class, function ($job) {
            return $job->deviceId === $this->wolDevice->id;
        });

        $this->assertLessThan(80.0, $latencyMs, "Latencia de respuesta excedió 80ms: {$latencyMs}ms");
    }

    public function test_sync_scan_dispatches_job_and_returns_202_under_80ms(): void
    {
        Queue::fake();

        $start = microtime(true);
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.sync.scan'));
        $latencyMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'status' => 'queued',
        ]);

        Queue::assertPushed(TriggerTelemetrySyncJob::class);
        $this->assertLessThan(80.0, $latencyMs, "Latencia de respuesta excedió 80ms: {$latencyMs}ms");
    }

    public function test_predictive_run_dispatches_job_and_returns_202_under_80ms(): void
    {
        Queue::fake();

        $start = microtime(true);
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.predictive.run'));
        $latencyMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'status' => 'queued',
        ]);

        Queue::assertPushed(AnalyzeNetworkPredictiveJob::class);
        $this->assertLessThan(80.0, $latencyMs, "Latencia de respuesta excedió 80ms: {$latencyMs}ms");
    }

    public function test_snmp_poll_and_discovery_dispatch_jobs_and_return_202_under_80ms(): void
    {
        Queue::fake();

        // 1. Trigger Poll
        $start = microtime(true);
        $responsePoll = $this->actingAs($this->adminUser)
            ->postJson(route('admin.snmp.poll', $this->snmpDevice->id));
        $pollLatencyMs = (microtime(true) - $start) * 1000;

        $responsePoll->assertStatus(202);
        $responsePoll->assertJson([
            'success' => true,
            'status' => 'queued',
        ]);

        // 2. Trigger Interface Discovery
        $start2 = microtime(true);
        $responseDisc = $this->actingAs($this->adminUser)
            ->postJson(route('admin.snmp.discover-interfaces', $this->snmpDevice->id));
        $discLatencyMs = (microtime(true) - $start2) * 1000;

        $responseDisc->assertStatus(202);
        $responseDisc->assertJson([
            'success' => true,
            'status' => 'queued',
        ]);

        Queue::assertPushed(SnmpDiscoveryJob::class, 2);
        $this->assertLessThan(80.0, $pollLatencyMs, "Latencia de Poll excedió 80ms: {$pollLatencyMs}ms");
        $this->assertLessThan(80.0, $discLatencyMs, "Latencia de Discovery excedió 80ms: {$discLatencyMs}ms");
    }

    public function test_all_jobs_handle_execution_cleanly(): void
    {
        \Illuminate\Support\Facades\Process::fake([
            '*' => \Illuminate\Support\Facades\Process::result(
                output: json_encode(['status' => 'ok', 'message' => 'Simulated Success']),
                exitCode: 0
            ),
        ]);

        // 1. WakeOnLanJob handle
        $wolJob = new WakeOnLanJob($this->wolDevice->id, $this->adminUser->id, '127.0.0.1');
        $wolJob->handle();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'wol',
            'auditable_id' => $this->wolDevice->id,
        ]);

        // 2. TriggerTelemetrySyncJob handle
        $syncJob = new TriggerTelemetrySyncJob($this->adminUser->id, '127.0.0.1');
        $syncJob->handle();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'sync',
            'entity_name' => 'Escaneo de Telemetría',
        ]);

        // 3. AnalyzeNetworkPredictiveJob handle
        $predJob = new AnalyzeNetworkPredictiveJob($this->adminUser->id, '127.0.0.1');
        $predJob->handle();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'predictive',
            'entity_name' => 'Análisis Predictivo',
        ]);

        // 4. SnmpDiscoveryJob handle
        $snmpJob = new SnmpDiscoveryJob($this->snmpDevice->id, 'poll', $this->adminUser->id, '127.0.0.1');
        $snmpJob->handle();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'snmp',
            'auditable_id' => $this->snmpDevice->id,
        ]);
    }
}
