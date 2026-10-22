<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StatelessTelemetryMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Prueba que una petición no autenticada a telemetría devuelve 401 JSON
     * y jamás contamina session('url.intended').
     */
    public function test_unauthenticated_ajax_telemetry_returns_401_and_clears_intended(): void
    {
        $response = $this->withSession(['url.intended' => 'http://localhost/admin/ups/live'])
            ->getJson('/admin/ups/live');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'unauthenticated' => true,
        ]);

        $this->assertNull(session('url.intended'));
    }

    /**
     * Prueba que una petición de quick status no autenticada no ensucie url.intended.
     */
    public function test_unauthenticated_quick_status_does_not_pollute_intended_session(): void
    {
        $response = $this->withSession(['url.intended' => 'http://localhost/admin/inventory/deploy/quick-status'])
            ->postJson('/admin/inventory/deploy/quick-status', ['ip' => '192.168.1.1']);

        $response->assertStatus(401);
        $this->assertNull(session('url.intended'));
    }

    /**
     * Prueba que un usuario autenticado con permisos puede consumir telemetría con normalidad.
     */
    public function test_authenticated_user_can_access_telemetry_cleanly(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $this->markTestSkipped('No hay usuario admin en la base de datos para esta prueba.');
        }

        $response = $this->actingAs($admin)->getJson('/admin/ups/live');
        $response->assertOk();
        $this->assertNull(session('url.intended'));
    }
}
