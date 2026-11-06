<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\RemoteAccess\WebsockifyTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Phase1SecurityHardeningV55330Test extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected string $tempTokensFile;
    protected WebsockifyTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempTokensFile = sys_get_temp_dir() . '/test_websockify_tokens_' . uniqid() . '.cfg';
        $this->tokenService = new WebsockifyTokenService($this->tempTokensFile, 50);
        $this->app->instance(WebsockifyTokenService::class, $this->tokenService);

        $this->adminUser = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);

        if (!$this->adminUser->terms_accepted_at) {
            $this->adminUser->update(['terms_accepted_at' => now()]);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempTokensFile)) {
            @unlink($this->tempTokensFile);
        }
        $meta = $this->tempTokensFile . '.meta.json';
        if (file_exists($meta)) {
            @unlink($meta);
        }
        parent::tearDown();
    }

    public function test_ssh_session_generates_token_and_audits_in_mariadb(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('admin.ssh.session'), [
            'ip' => '192.168.1.1',
            'port' => 22,
            'name' => 'Router Gateway',
            'site' => 'Sede Valle Seco',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('token=', $data['viewer_url']);

        parse_str(parse_url($data['viewer_url'], PHP_URL_QUERY), $queryParams);
        $token = $queryParams['token'] ?? '';
        $this->assertNotEmpty($token);

        // Validar token registrado en WebsockifyTokenService
        $tokenService = app(WebsockifyTokenService::class);
        $this->assertTrue($tokenService->isTokenValid($token));

        // Validar auditoría en MariaDB SSOT
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'event' => 'ssh_session',
            'module' => 'remote',
            'entity_name' => 'Consola Web SSH',
        ]);
    }

    public function test_ssh_terminal_embeds_token_in_iframe(): void
    {
        $tokenService = app(WebsockifyTokenService::class);
        $token = $tokenService->generateToken('ssh');
        $tokenService->registerToken($token, '192.168.1.1', 22, 120);

        $response = $this->actingAs($this->adminUser)->get(route('admin.ssh.terminal', [
            'ip' => '192.168.1.1',
            'port' => 22,
            'name' => 'Router Gateway',
            'site' => 'Sede Valle Seco',
            'token' => $token,
        ]));

        $response->assertStatus(200);
        $response->assertSee('/wssh/?hostname=192.168.1.1&port=22&token=' . urlencode($token), false);
    }

    public function test_host_header_poisoning_cannot_bypass_deploy_whitelist(): void
    {
        // Atacante intenta envenenar con cabecera Host: evil.com
        $responseWin = $this->withHeaders(['Host' => 'evil.com'])
            ->get('/deploy/win?server_url=http://evil.com/receiver&deploy_base=http://evil.com/instaladores');

        $responseWin->assertStatus(403);

        $responseLinux = $this->withHeaders(['Host' => 'attacker.org'])
            ->get('/deploy/linux?server_url=http://attacker.org/receiver&deploy_base=http://attacker.org/instaladores');

        $responseLinux->assertStatus(403);
    }

    public function test_legitimate_cluster_hosts_allowed_in_deploy(): void
    {
        $responseWin = $this->get('/deploy/win?server_url=http://192.168.1.220/api/v1/inventory/receiver&deploy_base=http://192.168.1.220/instaladores');
        $responseWin->assertStatus(200);

        $responseLinux = $this->get('/deploy/linux?server_url=http://192.168.1.250/api/v1/inventory/receiver&deploy_base=http://192.168.1.250/instaladores');
        $responseLinux->assertStatus(200);
    }
}
