<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class DeployRoutesSsrfTest extends TestCase
{
    public function test_deploy_routes_work_with_valid_urls(): void
    {
        $responseWin = $this->get('/deploy/win?server_url=https://glpi.example.com/receiver&deploy_base=https://glpi.example.com/instaladores');
        $responseWin->assertStatus(200);
        $responseWin->assertSee('https://glpi.example.com/receiver');

        $responseLinux = $this->get('/deploy/linux?server_url=https://glpi.example.com/receiver&deploy_base=https://glpi.example.com/instaladores');
        $responseLinux->assertStatus(200);
        $responseLinux->assertSee('https://glpi.example.com/receiver');
    }

    public function test_deploy_routes_reject_invalid_server_url_with_400(): void
    {
        $responseWin = $this->get('/deploy/win?server_url=not_a_valid_url');
        $responseWin->assertStatus(400);

        $responseLinux = $this->get('/deploy/linux?server_url=javascript:alert(1)');
        $responseLinux->assertStatus(400);
    }

    public function test_deploy_routes_reject_disallowed_schemes_with_400(): void
    {
        $responseWin = $this->get('/deploy/win?server_url=file:///etc/shadow');
        $responseWin->assertStatus(400);

        $responseLinux = $this->get('/deploy/linux?server_url=ftp://malicious.server/poc');
        $responseLinux->assertStatus(400);
    }

    public function test_deploy_routes_reject_invalid_deploy_base_with_400(): void
    {
        $responseWin = $this->get('/deploy/win?deploy_base=gopher://internal-service');
        $responseWin->assertStatus(400);

        $responseLinux = $this->get('/deploy/linux?deploy_base=invalid_scheme_url');
        $responseLinux->assertStatus(400);
    }
}
