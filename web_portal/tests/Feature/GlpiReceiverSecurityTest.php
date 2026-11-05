<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Services\GlpiInventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GlpiReceiverSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected string $testToken = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        BotSetting::set('glpi_receiver_token', $this->testToken, 'glpi_deploy', 'Test Token', 'text');
    }

    public function test_get_request_returns_healthy_status(): void
    {
        $response = $this->get('/api/v1/inventory/receiver');

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
    }

    public function test_post_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/inventory/receiver', [
            'action' => 'inventory',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Token criptográfico de agente GLPI inválido o ausente',
        ]);
    }

    public function test_post_with_legacy_token_returns_401(): void
    {
        // F-01: El token hardcodeado 'ValleSeco_GLPI_Token_2026_Secure' debe ser rechazado
        $response = $this->withHeaders([
            'X-Glpi-Agent-Token' => 'ValleSeco_GLPI_Token_2026_Secure',
        ])->postJson('/api/v1/inventory/receiver', [
            'action' => 'inventory',
        ]);

        $response->assertStatus(401);
    }

    public function test_post_with_query_param_token_returns_401(): void
    {
        // F-02: El token en URL (query param o path) no debe autorizar la petición
        $response = $this->postJson('/api/v1/inventory/receiver?token=' . $this->testToken, [
            'action' => 'inventory',
        ]);

        $response->assertStatus(401);
    }

    public function test_post_with_valid_x_glpi_agent_token_header_is_accepted(): void
    {
        $mockGlpi = $this->createMock(GlpiInventoryService::class);
        $mockGlpi->expects($this->once())
            ->method('processRawPayload')
            ->willReturn(['status' => 'ok', 'type' => 'prolog']);

        $this->app->instance(GlpiInventoryService::class, $mockGlpi);

        $response = $this->withHeaders([
            'X-Glpi-Agent-Token' => $this->testToken,
        ])->postJson('/api/v1/inventory/receiver', [
            'query' => 'prolog',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_post_with_valid_bearer_token_is_accepted(): void
    {
        $mockGlpi = $this->createMock(GlpiInventoryService::class);
        $mockGlpi->expects($this->once())
            ->method('processRawPayload')
            ->willReturn(['status' => 'ok', 'type' => 'prolog']);

        $this->app->instance(GlpiInventoryService::class, $mockGlpi);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->testToken,
        ])->postJson('/api/v1/inventory/receiver', [
            'query' => 'prolog',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_ip_spoofing_mitigation_passes_request_ip_to_service(): void
    {
        // F-10: X-Agent-IP spoofed no debe sobrescribir request->ip()
        $capturedIp = null;
        $mockGlpi = $this->createMock(GlpiInventoryService::class);
        $mockGlpi->expects($this->once())
            ->method('processRawPayload')
            ->willReturnCallback(function ($rawContent, $ipAddress) use (&$capturedIp) {
                $capturedIp = $ipAddress;
                return ['status' => 'ok', 'type' => 'prolog'];
            });

        $this->app->instance(GlpiInventoryService::class, $mockGlpi);

        $this->withHeaders([
            'X-Glpi-Agent-Token' => $this->testToken,
            'X-Agent-IP' => '10.20.99.99', // intento de spoofing
        ])->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.45',
        ])->postJson('/api/v1/inventory/receiver', [
            'query' => 'prolog',
        ]);

        $this->assertNotEquals('10.20.99.99', $capturedIp);
        $this->assertEquals('192.168.1.45', $capturedIp);
    }
}
