<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\GlpiEndpointConfigInterface;
use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GlpiEndpointsConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_glpi_endpoints_service_resolves_current_host_and_urls(): void
    {
        /** @var GlpiEndpointConfigInterface $service */
        $service = app(GlpiEndpointConfigInterface::class);

        $host = $service->getCurrentHost();
        $this->assertNotEmpty($host);
        $this->assertNotEquals('127.0.0.1', $host);
        $this->assertFalse(str_starts_with($host, '127.'));
        $this->assertNotEquals('localhost', $host);

        $config = $service->getEndpointsConfig();
        $this->assertArrayHasKey('current_host', $config);
        $this->assertArrayHasKey('current_url', $config);
        $this->assertArrayHasKey('prod_url', $config);
        $this->assertArrayHasKey('dev_url', $config);
        $this->assertArrayHasKey('token', $config);

        // Test auto/current resolution
        $auto = $service->resolveTargetUrl('auto');
        $this->assertEquals($config['current_url'], $auto['server_url']);
        $this->assertStringContainsString('Auto-detectado', $auto['target_label']);

        // Test prod resolution
        $prod = $service->resolveTargetUrl('prod');
        $this->assertEquals($config['prod_url'], $prod['server_url']);
        $this->assertStringContainsString('Producción', $prod['target_label']);

        // Test dev resolution
        $dev = $service->resolveTargetUrl('dev');
        $this->assertEquals($config['dev_url'], $dev['server_url']);
        $this->assertStringContainsString('Desarrollo', $dev['target_label']);

        // Test custom resolution with manual IP
        $custom = $service->resolveTargetUrl('custom', '192.168.1.244');
        $this->assertStringContainsString('192.168.1.244', $custom['server_url']);
        $this->assertStringContainsString('Personalizado (192.168.1.244)', $custom['target_label']);
    }

    public function test_admin_can_update_glpi_endpoints_via_api(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'is_active' => true, 'terms_accepted_at' => now()]);

        $newToken = 'a8f94e2b6c1d73a0e5f812349b6d8c07e2a419f85c3b17d0d8f58b76e1039c94';
        $newProdUrl = 'http://192.168.1.244/api/v1/inventory/receiver/' . $newToken;
        $newDevUrl = 'http://192.168.1.220/api/v1/inventory/receiver/' . $newToken;

        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.endpoints'), [
            'glpi_receiver_prod_url' => $newProdUrl,
            'glpi_receiver_dev_url' => $newDevUrl,
            'glpi_receiver_token' => $newToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals($newProdUrl, BotSetting::get('glpi_receiver_prod_url'));
        $this->assertEquals($newToken, BotSetting::get('glpi_receiver_token'));
    }

    public function test_non_admin_cannot_update_glpi_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
            'terms_accepted_at' => now(),
            'permissions' => [],
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.inventory.deploy.endpoints'), [
            'glpi_receiver_prod_url' => 'http://192.168.1.244/api',
            'glpi_receiver_dev_url' => 'http://192.168.1.220/api',
            'glpi_receiver_token' => 'ValidToken_12345',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_endpoints_validates_urls(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'is_active' => true, 'terms_accepted_at' => now()]);

        $response = $this->actingAs($admin)->postJson(route('admin.inventory.deploy.endpoints'), [
            'glpi_receiver_prod_url' => 'not-a-valid-url',
            'glpi_receiver_dev_url' => 'neither-valid',
            'glpi_receiver_token' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['glpi_receiver_prod_url', 'glpi_receiver_dev_url', 'glpi_receiver_token']);
    }

    public function test_deploy_view_renders_endpoints_configuration_modal_and_target_options(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'is_active' => true, 'terms_accepted_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.inventory.deploy'));

        $response->assertStatus(200);
        $response->assertSee('id="modal-endpoints-config"', false);
        $response->assertSee('name="target" value="auto"', false);
        $response->assertSee('name="target" value="custom"', false);
        $response->assertSee('name="custom_ip"', false);
        $response->assertSee('name="bulk_custom_ip"', false);
        $response->assertSee('openEndpointsConfigModal', false);
    }
}
