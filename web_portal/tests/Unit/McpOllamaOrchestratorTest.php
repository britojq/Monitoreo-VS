<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\OllamaServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpOllamaOrchestratorTest extends TestCase
{
    protected OllamaServiceInterface $ollamaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ollamaService = app(OllamaServiceInterface::class);
    }

    public function test_ollama_service_returns_system_prompt_with_security_guardrails(): void
    {
        $prompt = $this->ollamaService->getSystemPrompt('Operador NOC', 'Operador');

        $this->assertStringContainsString('Monitor Valle Seco', $prompt);
        $this->assertStringContainsString('Operador NOC', $prompt);
        // Regla de Oro #2: Prohibido mencionar tecnologías internas
        $this->assertStringNotContainsString('Ollama', $prompt);
        $this->assertStringNotContainsString('MariaDB', $prompt);
        $this->assertStringNotContainsString('MySQL', $prompt);
    }

    public function test_ollama_service_returns_tool_schema_with_five_tools(): void
    {
        $schema = $this->ollamaService->getToolSchema();

        $this->assertCount(5, $schema);
        $toolNames = array_map(fn($t) => $t['function']['name'], $schema);

        $this->assertContains('get_inventory_summary', $toolNames);
        $this->assertContains('get_ups_telemetry', $toolNames);
        $this->assertContains('search_itam_asset', $toolNames);
        $this->assertContains('get_noc_site_status', $toolNames);
        $this->assertContains('get_proxy_status', $toolNames);
    }

    public function test_ollama_service_handles_direct_text_response(): void
    {
        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Soy Monitor Valle Seco, el asistente virtual corporativo de La Sede Valle Seco.',
                ],
            ], 200),
        ]);

        $res = $this->ollamaService->chat('Hola, ¿quién eres?');

        $this->assertTrue($res['success']);
        $this->assertStringContainsString('Monitor Valle Seco', $res['reply']);
        $this->assertNull($res['tool_executed']);
    }

    public function test_ollama_service_sanitizes_forbidden_technology_names_in_output(): void
    {
        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Ejecuto sobre Ollama y consulto MariaDB directamente.',
                ],
            ], 200),
        ]);

        $res = $this->ollamaService->chat('¿Cuál es tu backend?');

        $this->assertTrue($res['success']);
        $this->assertStringNotContainsStringIgnoringCase('Ollama', $res['reply']);
        $this->assertStringNotContainsStringIgnoringCase('MariaDB', $res['reply']);
        $this->assertStringContainsString('motor local de IA', $res['reply']);
        $this->assertStringContainsString('base de datos corporativa', $res['reply']);
    }

    public function test_ollama_service_executes_tool_calling_loop(): void
    {
        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::sequence()
                // 1. Primera llamada: Ollama decide invocar la tool
                ->push([
                    'message' => [
                        'role' => 'assistant',
                        'content' => '',
                        'tool_calls' => [
                            [
                                'id' => 'call_test_123',
                                'function' => [
                                    'name' => 'get_ups_telemetry',
                                    'arguments' => ['ups_id' => 'LV6KL-VS-01'],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                // 2. Segunda llamada: Ollama recibe el JSON de la tool y sintetiza
                ->push([
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'El UPS LV6KL-VS-01 se encuentra 100% ONLINE y en LINE_MODE con batería al 100%.',
                    ],
                ], 200),
        ]);

        $res = $this->ollamaService->chat('¿Cuál es el estado del UPS?');

        $this->assertTrue($res['success']);
        $this->assertEquals('get_ups_telemetry', $res['tool_executed']);
        $this->assertNotNull($res['tool_result']);
        $this->assertEquals('LV6KL-VS-01', $res['tool_result']['ups_id']);
        $this->assertStringContainsString('100% ONLINE', $res['reply']);
    }

    public function test_ai_assistant_controller_api_chat_rejects_missing_or_invalid_cluster_token(): void
    {
        $response = $this->postJson(route('api.ai.chat'), [
            'message' => 'Consulta no autorizada',
        ]);

        $response->assertStatus(404);
    }

    public function test_ai_assistant_controller_api_chat_processes_authorized_request(): void
    {
        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Respuesta autorizada del cluster MCP.',
                ],
            ], 200),
        ]);

        $clusterToken = (new \App\Services\ClusterConfigService())->getClusterToken();

        $response = $this->withHeaders([
            'X-Cluster-Token' => $clusterToken,
        ])->postJson(route('api.ai.chat'), [
            'message' => 'Consulta autorizada desde cluster',
            'user_name' => 'Operador NOC Telegram',
            'user_role' => 'Administrador',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Respuesta autorizada del cluster MCP.',
            ]);
    }

    public function test_group_context_disables_tools_and_uses_technical_support_prompt(): void
    {
        $prompt = $this->ollamaService->getSystemPrompt('Grupo Corporativo', 'Grupo Corporativo');

        $this->assertStringContainsString('Asistente Técnico Consultivo', $prompt);
        $this->assertStringContainsString('TERMINANTEMENTE PROHIBIDO divulgar o responder sobre datos internos del sistema', $prompt);
        $this->assertStringContainsString('confidencial y de acceso exclusivo para el Administrador', $prompt);

        Http::fake([
            'http://127.0.0.1:11434/api/chat' => function (\Illuminate\Http\Client\Request $request) {
                // Verificar que no se adjunten herramientas de infraestructura para el grupo
                $data = $request->data();
                $this->assertArrayNotHasKey('tools', $data);

                return Http::response([
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Para configurar VLANs en Linux se utiliza el comando ip link add link...',
                    ],
                ], 200);
            },
        ]);

        $res = $this->ollamaService->chat(
            userMessage: '¿Cómo configuro una VLAN en Linux?',
            userName: 'Grupo Corporativo',
            userRole: 'Grupo Corporativo'
        );

        $this->assertTrue($res['success']);
        $this->assertNull($res['tool_executed']);
        $this->assertStringContainsString('ip link add link', $res['reply']);
    }
}
