<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PredictiveAnomaly;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminPredictiveTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);
    }

    public function test_predictive_index_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.predictive.index'));

        $response->assertStatus(200);
        $response->assertSee('Inteligencia Artificial Predictiva', false);
    }

    public function test_run_predictive_analysis_creates_valid_audit_log_without_sql_error(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.predictive.run'));

        $response->assertRedirect(route('admin.predictive.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'event' => 'executed',
            'module' => 'predictive',
        ]);
    }

    public function test_acknowledge_and_delete_anomaly_creates_valid_audit_logs(): void
    {
        $anomaly = PredictiveAnomaly::create([
            'entity_type' => 'service',
            'entity_id' => 1,
            'anomaly_type' => 'outlier',
            'metric_name' => 'latency_ms',
            'confidence' => 95.5,
            'severity' => 'warning',
            'description' => 'Anomalía de prueba temporal',
            'acknowledged' => false,
            'detected_at' => now(),
        ]);

        // 1. Probar Acknowledge
        $ackResponse = $this->actingAs($this->adminUser)->post(route('admin.predictive.acknowledge', $anomaly->id));
        $ackResponse->assertRedirect(route('admin.predictive.index'));

        $anomaly->refresh();
        $this->assertTrue((bool) $anomaly->acknowledged);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'event' => 'acknowledged',
            'module' => 'predictive',
            'auditable_id' => $anomaly->id,
        ]);

        // 2. Probar Destroy
        $delResponse = $this->actingAs($this->adminUser)->delete(route('admin.predictive.destroy', $anomaly->id));
        $delResponse->assertRedirect(route('admin.predictive.index'));

        $this->assertDatabaseMissing('predictive_anomalies', [
            'id' => $anomaly->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'event' => 'deleted',
            'module' => 'predictive',
            'auditable_id' => $anomaly->id,
        ]);
    }
}
