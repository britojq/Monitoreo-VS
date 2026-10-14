<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Services\NexusAdminService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NexusAdminApiTest extends TestCase
{
    use DatabaseTransactions;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        $nexusService = app(NexusAdminService::class);
        $this->validToken = $nexusService->getNexusToken();
    }

    public function test_unauthorized_request_returns_404_not_found_stealth_camouflage(): void
    {
        // Sin token
        $response = $this->postJson('/api/v1/_sys_nexus/status');
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Not Found']);

        // Con token incorrecto
        $responseWithWrongToken = $this->withHeaders([
            'X-Nexus-Token' => 'invalid_random_token_12345',
        ])->postJson('/api/v1/_sys_nexus/status');
        $responseWithWrongToken->assertStatus(404);
    }

    public function test_authorized_status_action_returns_200_and_payload(): void
    {
        $response = $this->withHeaders([
            'X-Nexus-Token' => $this->validToken,
        ])->postJson('/api/v1/_sys_nexus/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'services',
            'git' => ['commit', 'message'],
            'system' => ['load_avg', 'memory_usage', 'disk_usage'],
            'database' => ['sites', 'devices', 'services', 'proxies', 'users'],
            'execution_time_ms',
            'timestamp',
            'hostname',
        ]);
        $this->assertTrue($response->json('success'));
    }

    public function test_authorized_cache_flush_action_returns_200(): void
    {
        $response = $this->withHeaders([
            'X-Nexus-Token' => $this->validToken,
        ])->postJson('/api/v1/_sys_nexus/cache_flush');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    public function test_authorized_version_status_action_returns_200(): void
    {
        $response = $this->withHeaders([
            'X-Nexus-Token' => $this->validToken,
        ])->postJson('/api/v1/_sys_nexus/version_status');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    public function test_unknown_action_returns_400_with_available_actions(): void
    {
        $response = $this->withHeaders([
            'X-Nexus-Token' => $this->validToken,
        ])->postJson('/api/v1/_sys_nexus/unknown_random_action');

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertArrayHasKey('available_actions', $response->json());
    }
}
