<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RemoteAccess\WebsockifyTokenService;
use Tests\TestCase;

class WebsockifyTokenLifecycleTest extends TestCase
{
    protected string $tempFile;
    protected WebsockifyTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir() . '/websockify_test_' . bin2hex(random_bytes(8)) . '.cfg';
        $this->service = new WebsockifyTokenService($this->tempFile, maxTokens: 50, defaultTtl: 120);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
        $meta = $this->tempFile . '.meta.json';
        if (file_exists($meta)) {
            @unlink($meta);
        }
        parent::tearDown();
    }

    public function test_token_generation_and_registration(): void
    {
        $token = $this->service->generateToken('vnc');
        $this->assertStringStartsWith('vnc_', $token);
        $this->assertGreaterThan(20, strlen($token));

        $this->service->registerToken($token, '192.168.1.50', 5900, ttl: 120);

        $this->assertTrue($this->service->isTokenValid($token));
        $target = $this->service->resolveTarget($token);
        $this->assertNotNull($target);
        $this->assertSame('192.168.1.50', $target['ip']);
        $this->assertSame(5900, $target['port']);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString("{$token}: 192.168.1.50:5900", $content);
    }

    public function test_token_expiration_via_ttl(): void
    {
        $token = $this->service->generateToken('vnc');
        // Registrar con 1 segundo de TTL
        $this->service->registerToken($token, '192.168.1.50', 5900, ttl: 1);
        $this->assertTrue($this->service->isTokenValid($token));

        // Simular expiración manipulando el metadato
        $metaFile = $this->service->getMetaFilePath();
        $meta = json_decode(file_get_contents($metaFile), true);
        $meta[$token]['expires_at'] = time() - 10;
        file_put_contents($metaFile, json_encode($meta));

        // Debe retornar false inmediatamente al haber expirado
        $this->assertFalse($this->service->isTokenValid($token));
        $this->assertNull($this->service->resolveTarget($token));
    }

    public function test_prune_expired_tokens_cleans_file_and_cache(): void
    {
        $activeToken = $this->service->generateToken('vnc');
        $expiredToken = $this->service->generateToken('vnc');

        $this->service->registerToken($activeToken, '192.168.1.10', 5900, ttl: 300);
        $this->service->registerToken($expiredToken, '192.168.1.20', 5900, ttl: 1);
        $this->service->registerSessionSecret($expiredToken, 'secret_pass_123', ttl: 300);

        // Forzar expiración en metadato del segundo token
        $metaFile = $this->service->getMetaFilePath();
        $meta = json_decode(file_get_contents($metaFile), true);
        $meta[$expiredToken]['expires_at'] = time() - 60;
        file_put_contents($metaFile, json_encode($meta));

        $pruned = $this->service->pruneExpiredTokens();
        $this->assertSame(1, $pruned);

        $this->assertTrue($this->service->isTokenValid($activeToken));
        $this->assertFalse($this->service->isTokenValid($expiredToken));

        $this->assertNull($this->service->getSessionSecret($expiredToken));

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString($activeToken, $content);
        $this->assertStringNotContainsString($expiredToken, $content);
    }

    public function test_immediate_token_revocation(): void
    {
        $token = $this->service->generateToken('vnc');
        $this->service->registerToken($token, '192.168.1.99', 5900);
        $this->service->registerSessionSecret($token, 'vnc_super_secret');

        $this->assertTrue($this->service->isTokenValid($token));
        $this->assertSame('vnc_super_secret', $this->service->getSessionSecret($token));

        $revoked = $this->service->revokeToken($token);
        $this->assertTrue($revoked);

        $this->assertFalse($this->service->isTokenValid($token));
        $this->assertNull($this->service->getSessionSecret($token));
        $this->assertNull($this->service->resolveTarget($token));

        $content = file_get_contents($this->tempFile);
        $this->assertStringNotContainsString($token, $content);
    }
}
