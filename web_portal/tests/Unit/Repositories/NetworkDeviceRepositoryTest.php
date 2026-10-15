<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Contracts\Repositories\NetworkDeviceRepositoryInterface;
use App\DTOs\NetworkDeviceData;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use Tests\TestCase;

class NetworkDeviceRepositoryTest extends TestCase
{
    private NetworkDeviceRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(NetworkDeviceRepositoryInterface::class);
    }

    public function test_it_can_create_and_find_network_device(): void
    {
        $site = MonitoredSite::first();
        $siteId = $site ? $site->id : 1;
        $deviceNum = $this->repository->getNextDeviceNumber($siteId);

        $data = new NetworkDeviceData(
            id: null,
            monitoredSiteId: $siteId,
            deviceNumber: $deviceNum,
            name: 'ROUTER BACKBONE SOLID',
            ip: '10.20.23.250',
            mac: 'AA:BB:CC:DD:EE:FF',
            accessType: 'SIN SOPORTE',
        );

        $created = $this->repository->create($data);

        $this->assertInstanceOf(MonitoredNetworkDevice::class, $created);
        $this->assertEquals('ROUTER BACKBONE SOLID', $created->name);
        $this->assertEquals('10.20.23.250', $created->ip);

        $foundById = $this->repository->findById($created->id);
        $this->assertNotNull($foundById);
        $this->assertEquals('ROUTER BACKBONE SOLID', $foundById->name);

        $foundByIp = $this->repository->findByIp('10.20.23.250');
        $this->assertNotNull($foundByIp);
        $this->assertEquals($created->id, $foundByIp->id);

        $foundByMac = $this->repository->findByMac('AA:BB:CC:DD:EE:FF');
        $this->assertNotNull($foundByMac);
        $this->assertEquals($created->id, $foundByMac->id);
    }

    public function test_it_can_get_devices_by_site_id(): void
    {
        $site = MonitoredSite::first();
        if (!$site) {
            $this->markTestSkipped('No sites available.');
        }

        $devices = $this->repository->getBySiteId($site->id);
        $this->assertNotNull($devices);
    }
}
