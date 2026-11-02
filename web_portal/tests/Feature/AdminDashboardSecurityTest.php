<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Services\CronConfigServiceInterface;
use App\Http\Middleware\CheckBannedIp;
use App\Models\BannedIp;
use App\Models\User;
use App\Services\Cron\CronConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminDashboardSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ioc_resolves_cron_config_service_interface(): void
    {
        $service = app(CronConfigServiceInterface::class);
        $this->assertInstanceOf(CronConfigService::class, $service);
        $config = $service->getCronConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('schedules', $config);
    }

    public function test_view_dashboard_gate_allows_admin(): void
    {
        $admin = new User(['role' => 'admin']);
        $this->assertTrue(Gate::forUser($admin)->allows('view-dashboard'));
    }

    public function test_view_dashboard_gate_allows_user_with_infra_view(): void
    {
        $operator = new User([
            'role' => 'operator',
            'permissions' => ['infra.view'],
        ]);
        $this->assertTrue(Gate::forUser($operator)->allows('view-dashboard'));
    }

    public function test_view_dashboard_gate_denies_unauthorized_user(): void
    {
        $user = new User([
            'role' => 'user',
            'permissions' => json_encode(['itam.view']),
        ]);
        $this->assertFalse(Gate::forUser($user)->allows('view-dashboard'));
    }

    public function test_check_banned_ip_middleware_allows_whitelisted_ips(): void
    {
        config(['security.whitelist_ips' => ['192.168.100.50']]);

        // Insert banned IP that matches whitelist
        BannedIp::create([
            'ip_address' => '192.168.100.50',
            'reason' => 'Test banned but whitelisted',
            'banned_at' => now(),
        ]);

        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.100.50']);
        $middleware = new CheckBannedIp();

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_check_banned_ip_middleware_blocks_banned_non_whitelisted_ip(): void
    {
        config(['security.whitelist_ips' => ['127.0.0.1', '::1']]);

        BannedIp::create([
            'ip_address' => '203.0.113.199',
            'reason' => 'Malicious activity simulation',
            'banned_at' => now(),
        ]);

        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.199']);
        $middleware = new CheckBannedIp();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, function ($req) {
            return new Response('OK');
        });
    }

    public function test_dashboard_controller_renders_successfully_for_authenticated_admin(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::create([
                'name' => 'Test Admin',
                'username' => 'test_admin_sec_' . uniqid(),
                'email' => 'admin_sec_' . uniqid() . '@monitoreo.test',
                'password' => bcrypt('secret123'),
                'role' => 'admin',
            ]);
        }

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertViewHas('cronConfig');
        $response->assertViewHas('stats');
    }
}
