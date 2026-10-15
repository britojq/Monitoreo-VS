<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Contracts\Repositories\SiteRepositoryInterface;
use App\DTOs\SiteData;
use App\Models\MonitoredSite;
use Tests\TestCase;

class SiteRepositoryTest extends TestCase
{
    private SiteRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(SiteRepositoryInterface::class);
    }

    public function test_it_can_create_and_find_site(): void
    {
        $data = new SiteData(
            id: null,
            letter: 'Z',
            name: 'SEDE TEMPORAL SOLID',
            ip: '10.20.90.1',
            address: 'Edificio Central Test',
        );

        $created = $this->repository->create($data);

        $this->assertInstanceOf(MonitoredSite::class, $created);
        $this->assertEquals('Z', $created->letter);
        $this->assertEquals('SEDE TEMPORAL SOLID', $created->name);

        $foundById = $this->repository->findById($created->id);
        $this->assertNotNull($foundById);
        $this->assertEquals('Z', $foundById->letter);

        $foundByLetter = $this->repository->findByLetter('Z');
        $this->assertNotNull($foundByLetter);
        $this->assertEquals($created->id, $foundByLetter->id);
    }

    public function test_it_can_list_active_sites_with_devices(): void
    {
        $sites = $this->repository->getAllWithDevices();
        $this->assertNotNull($sites);
        $this->assertGreaterThan(0, $sites->count());
    }
}
