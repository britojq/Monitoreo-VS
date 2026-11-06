<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Services\ClusterConfigInterface;
use App\Services\ClusterConfigService;
use Tests\TestCase;

class ClusterConfigInterfaceTest extends TestCase
{
    public function test_cluster_config_interface_resolves_from_container(): void
    {
        $service = app(ClusterConfigInterface::class);

        $this->assertInstanceOf(ClusterConfigInterface::class, $service);
        $this->assertInstanceOf(ClusterConfigService::class, $service);
    }

    public function test_cluster_config_interface_is_singleton(): void
    {
        $instanceA = app(ClusterConfigInterface::class);
        $instanceB = app(ClusterConfigInterface::class);

        $this->assertSame($instanceA, $instanceB);
    }

    public function test_cluster_config_contract_methods(): void
    {
        $service = app(ClusterConfigInterface::class);

        $this->assertIsArray($service->getConfig());
        $this->assertIsString($service->getNodeRole());
        $this->assertIsBool($service->isMaster());
        $this->assertIsBool($service->isSlave());
        $this->assertIsBool($service->isDev());
        $this->assertIsString($service->getClusterToken());
        $this->assertIsString($service->getMasterApiUrl());
    }
}
