<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UpsDevice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'testing']);
    }

    public function test_login_discards_intended_url_if_it_is_live_polling_endpoint(): void
    {
        $user = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'password' => bcrypt('password123'),
        ]);

        // Simular que el navegador tenía en sesión url.intended = /admin/ups/live
        $response = $this->withSession(['url.intended' => route('admin.ups.live')])
            ->post(route('login.submit'), [
                'login' => $user->email,
                'password' => 'PeneloPe91*',
            ]);

        // Debe redirigir al dashboard, NUNCA a /admin/ups/live
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_direct_browser_navigation_to_live_endpoint_redirects_to_ups_index(): void
    {
        $user = User::where('role', 'admin')->first();

        // Petición con Accept text/html como navegador
        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'text/html'])
            ->get(route('admin.ups.live'));

        $response->assertRedirect(route('admin.ups.index'));
    }

    public function test_ajax_poll_to_live_endpoint_returns_json(): void
    {
        $user = User::where('role', 'admin')->first();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ups.live'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'device']);
    }
}
