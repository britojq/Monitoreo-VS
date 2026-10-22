<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\RemoteAccess\WebsockifyTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RemoteSessionAuditAndWebsockifyTest extends TestCase
{
    use DatabaseTransactions;

    protected string $tempTokensFile;
    protected WebsockifyTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();

        // Usar archivo temporal para pruebas de tokens websockify sin requerir permisos en /etc
        $this->tempTokensFile = sys_get_temp_dir() . '/test_websockify_tokens_' . uniqid() . '.cfg';
        $this->tokenService = new WebsockifyTokenService($this->tempTokensFile, 10);
        $this->app->instance(WebsockifyTokenService::class, $this->tokenService);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempTokensFile)) {
            @unlink($this->tempTokensFile);
        }
        parent::tearDown();
    }

    public function test_websockify_token_service_lifecycle(): void
    {
        $token = $this->tokenService->generateToken('vnc');
        $this->assertStringStartsWith('vnc_', $token);

        // Registrar token
        $this->tokenService->registerToken($token, '192.168.1.100', 5900);

        // Validar token
        $this->assertTrue($this->tokenService->isTokenValid($token));
        $this->assertFalse($this->tokenService->isTokenValid('token_inexistente'));

        // Resolver destino
        $target = $this->tokenService->resolveTarget($token);
        $this->assertNotNull($target);
        $this->assertEquals('192.168.1.100', $target['ip']);
        $this->assertEquals(5900, $target['port']);

        // Revocar token
        $this->assertTrue($this->tokenService->revokeToken($token));
        $this->assertFalse($this->tokenService->isTokenValid($token));
    }

    public function test_websockify_token_service_prunes_excess_tokens(): void
    {
        // Registrar 15 tokens cuando el límite es 10
        for ($i = 1; $i <= 15; $i++) {
            $this->tokenService->registerToken("tok_{$i}", "192.168.1.{$i}", 5900);
        }

        $lines = file($this->tempTokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertLessThanOrEqual(10, count($lines));
        $this->assertTrue($this->tokenService->isTokenValid('tok_15'));
    }

    public function test_vnc_session_creation_registers_token_and_audits_to_mariadb(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'terms_accepted_at' => now(),
            'permissions' => ['remote.vnc'],
        ]);

        $asset = InventoryAsset::create([
            'name' => 'TAQUILLA-01',
            'type' => 'desktop',
            'status' => 'in_stock',
            'ip_address' => '192.168.1.41',
            'vnc_port' => 5900,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.vnc.session'), [
            'ip' => '192.168.1.41',
            'port' => 5900,
            'name' => 'TAQUILLA-01',
            'site' => 'Sede Central',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'token', 'viewer_url', 'novnc_url']);

        $token = $response->json('token');
        $this->assertTrue($this->tokenService->isTokenValid($token));

        // Verificar que se registró formalmente en MariaDB audit_logs (SSOT)
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vnc_session',
            'module' => 'remote',
            'user_id' => $admin->id,
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
        ]);

        $audit = AuditLog::where('event', 'vnc_session')->where('user_id', $admin->id)->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('Escritorio Web VNC', $audit->event_label);
        $this->assertEquals('Acceso Remoto', $audit->module_label);
        $this->assertStringContainsString('TAQUILLA-01', $audit->description);
        $this->assertEquals('192.168.1.41', $audit->new_values['target_ip']);
    }

    public function test_telnet_session_creation_registers_token_and_audits_to_mariadb(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
            'terms_accepted_at' => now(),
            'permissions' => ['remote.telnet'],
        ]);

        $response = $this->actingAs($operator)->postJson(route('admin.telnet.session'), [
            'ip' => '192.168.1.1',
            'port' => 23,
            'name' => 'ROUTER-PRINCIPAL',
            'site' => 'Sede Central',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'token', 'viewer_url']);

        $token = $response->json('token');
        $this->assertTrue($this->tokenService->isTokenValid($token));

        // Verificar auditoría en MariaDB
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'telnet_session',
            'module' => 'remote',
            'user_id' => $operator->id,
        ]);

        $audit = AuditLog::where('event', 'telnet_session')->where('user_id', $operator->id)->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('Terminal Web Telnet', $audit->event_label);
        $this->assertStringContainsString('ROUTER-PRINCIPAL', $audit->description);
    }

    public function test_ssh_session_creation_audits_to_mariadb(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
            'terms_accepted_at' => now(),
            'permissions' => ['remote.ssh'],
        ]);

        $response = $this->actingAs($operator)->postJson(route('admin.ssh.session'), [
            'ip' => '192.168.1.10',
            'port' => 22,
            'name' => 'SERVIDOR-BD',
            'site' => 'Sede Central',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'viewer_url']);

        // Verificar auditoría en MariaDB
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ssh_session',
            'module' => 'remote',
            'user_id' => $operator->id,
        ]);

        $audit = AuditLog::where('event', 'ssh_session')->where('user_id', $operator->id)->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('Consola Web SSH', $audit->event_label);
        $this->assertStringContainsString('SERVIDOR-BD', $audit->description);
    }

    public function test_unauthorized_user_cannot_create_remote_sessions(): void
    {
        $userWithoutPerms = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
            'terms_accepted_at' => now(),
            'permissions' => [], // Sin permisos de acceso remoto
        ]);

        $vncResponse = $this->actingAs($userWithoutPerms)->postJson(route('admin.vnc.session'), [
            'ip' => '192.168.1.41',
        ]);
        $vncResponse->assertStatus(403);

        $telnetResponse = $this->actingAs($userWithoutPerms)->postJson(route('admin.telnet.session'), [
            'ip' => '192.168.1.1',
        ]);
        $telnetResponse->assertStatus(403);

        $sshResponse = $this->actingAs($userWithoutPerms)->postJson(route('admin.ssh.session'), [
            'ip' => '192.168.1.10',
        ]);
        $sshResponse->assertStatus(403);
    }

    public function test_vnc_viewer_requires_valid_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);

        $token = $this->tokenService->generateToken('vnc');
        $this->tokenService->registerToken($token, '192.168.1.41', 5900);

        // Con token válido: 200
        $response = $this->actingAs($admin)->get(route('admin.vnc.viewer', [
            'token' => $token,
            'ip' => '192.168.1.41',
        ]));
        $response->assertStatus(200);

        // Con token inválido: 404
        $invalidResponse = $this->actingAs($admin)->get(route('admin.vnc.viewer', [
            'token' => 'token_falso_expirado',
            'ip' => '192.168.1.41',
        ]));
        $invalidResponse->assertStatus(404);
    }

    public function test_telnet_terminal_requires_valid_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);

        $token = $this->tokenService->generateToken('telnet');
        $this->tokenService->registerToken($token, '192.168.1.1', 23);

        // Con token válido: 200
        $response = $this->actingAs($admin)->get(route('admin.telnet.terminal', [
            'token' => $token,
            'ip' => '192.168.1.1',
        ]));
        $response->assertStatus(200);

        // Con token inválido: 404
        $invalidResponse = $this->actingAs($admin)->get(route('admin.telnet.terminal', [
            'token' => 'token_falso_expirado',
            'ip' => '192.168.1.1',
        ]));
        $invalidResponse->assertStatus(404);
    }
}
