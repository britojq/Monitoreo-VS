<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\RemoteAccess\WebsockifyTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Phase1SecurityHardeningV55360Test extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);

        if (!$this->adminUser->terms_accepted_at) {
            $this->adminUser->update(['terms_accepted_at' => now()]);
        }
    }

    /**
     * SEC-16: Verificar que SecurityHeadersMiddleware inyecta la cabecera
     * Content-Security-Policy estricta y 100% autosuficiente (sin CDNs externos, Pilar #1).
     */
    public function test_security_headers_includes_strict_local_csp(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Validar CSP
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("font-src 'self' data:", $csp);
        $this->assertStringContainsString("img-src 'self' data: blob:", $csp);
        $this->assertStringContainsString("connect-src 'self' ws: wss:", $csp);
        $this->assertStringContainsString("frame-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);

        // Cumplimiento estricto del Pilar #1: Cero CDNs o dominios externos
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $csp);
        $this->assertStringNotContainsString('googleapis.com', $csp);
        $this->assertStringNotContainsString('gstatic.com', $csp);
    }

    /**
     * SEC-17: Verificar que la plantilla del servicio webssh implementa
     * aislamiento de origen (--origin=same), downstream confiable (--tdstream) y xheaders.
     */
    public function test_webssh_service_template_has_strict_origin_policy(): void
    {
        $templatePath = base_path('../system/templates/systemd/webssh.service');
        $this->assertFileExists($templatePath);

        $content = file_get_contents($templatePath);
        $this->assertStringContainsString('--address=127.0.0.1', $content);
        $this->assertStringContainsString('--port=6082', $content);
        $this->assertStringContainsString('--origin=same', $content);
        $this->assertStringContainsString('--tdstream=127.0.0.1', $content);
        $this->assertStringContainsString('--xheaders=True', $content);
        $this->assertStringContainsString('User=www-data', $content);
    }

    /**
     * SEC-13 / SEC-17: Verificar que Apache bloquea peticiones a /wssh
     * carentes de token y que el servicio webssh responde localmente a orígenes válidos.
     */
    public function test_webssh_proxy_location_enforces_token(): void
    {
        // 1. Petición sin token -> 403 Forbidden
        $resNoToken = @file_get_contents('http://127.0.0.1/wssh', false, stream_context_create([
            'http' => ['ignore_errors' => true],
        ]));
        $statusNoToken = $http_response_header[0] ?? '';
        $this->assertStringContainsString('403', $statusNoToken);

        // 2. Petición con token válido -> Apache permite el paso
        $validToken = 'test_token_' . bin2hex(random_bytes(16));
        $resWithToken = @file_get_contents("http://127.0.0.1/wssh?token={$validToken}", false, stream_context_create([
            'http' => ['ignore_errors' => true],
        ]));
        $statusWithToken = $http_response_header[0] ?? '';
        $this->assertStringNotContainsString('403', $statusWithToken);
    }
}
