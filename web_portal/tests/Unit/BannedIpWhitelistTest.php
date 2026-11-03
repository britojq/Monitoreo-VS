<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BannedIp;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BannedIpWhitelistTest extends TestCase
{
    public function test_whitelist_ips_are_loaded_from_security_config(): void
    {
        Config::set('security.whitelist_ips', ['192.168.100.1', '192.168.100.2']);

        $whitelist = BannedIp::getWhitelistIps();

        $this->assertContains('192.168.100.1', $whitelist);
        $this->assertContains('192.168.100.2', $whitelist);
    }

    public function test_whitelisted_ips_are_immune_to_is_banned_check(): void
    {
        Config::set('security.whitelist_ips', ['10.50.0.1', '10.50.0.2']);

        $this->assertFalse(BannedIp::isBanned('10.50.0.1'));
        $this->assertFalse(BannedIp::isBanned('10.50.0.2'));
    }

    public function test_loopback_and_empty_ips_are_safe(): void
    {
        $this->assertFalse(BannedIp::isBanned(null));
        $this->assertFalse(BannedIp::isBanned(''));
        $this->assertFalse(BannedIp::isBanned('127.0.0.1'));
        $this->assertFalse(BannedIp::isBanned('127.0.0.50'));
    }
}
