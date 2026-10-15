<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\DTOs\InventoryAssetData;
use App\Models\InventoryAsset;
use App\Models\MonitoredSite;
use Tests\TestCase;

class InventoryAssetRepositoryTest extends TestCase
{
    private InventoryAssetRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(InventoryAssetRepositoryInterface::class);
    }

    public function test_it_can_create_and_find_an_inventory_asset(): void
    {
        $site = MonitoredSite::first();
        $siteId = $site ? $site->id : null;

        $data = new InventoryAssetData(
            id: null,
            assetTag: 'AST-TEST-9901',
            name: 'PUESTO AUDITORIA SOLID',
            type: 'workstation',
            status: 'in_use',
            brand: 'Dell',
            model: 'OptiPlex 7090',
            serialNumber: 'SN-SOLID-101',
            macAddress: '00:11:22:33:44:55',
            ipAddress: '10.20.23.199',
            monitoredSiteId: $siteId,
            responsibleName: 'Ingeniero de Sistemas',
        );

        $created = $this->repository->create($data);

        $this->assertInstanceOf(InventoryAsset::class, $created);
        $this->assertEquals('PUESTO AUDITORIA SOLID', $created->name);
        $this->assertEquals('AST-TEST-9901', $created->asset_tag);

        $foundById = $this->repository->findById($created->id);
        $this->assertNotNull($foundById);
        $this->assertEquals('PUESTO AUDITORIA SOLID', $foundById->name);

        $foundByTag = $this->repository->findByAssetTag('AST-TEST-9901');
        $this->assertNotNull($foundByTag);
        $this->assertEquals($created->id, $foundByTag->id);

        $foundByIp = $this->repository->findByIp('10.20.23.199');
        $this->assertNotNull($foundByIp);
        $this->assertEquals($created->id, $foundByIp->id);

        $foundByMac = $this->repository->findByMac('00:11:22:33:44:55');
        $this->assertNotNull($foundByMac);
        $this->assertEquals($created->id, $foundByMac->id);
    }

    public function test_it_can_update_an_inventory_asset(): void
    {
        $asset = InventoryAsset::first();
        if (!$asset) {
            $this->markTestSkipped('No inventory assets exist to test update.');
        }

        $originalName = $asset->name;
        $updatedData = new InventoryAssetData(
            id: $asset->id,
            assetTag: $asset->asset_tag,
            name: $originalName . ' (REPOSITORIO ACTUALIZADO)',
            type: $asset->type,
            status: $asset->status,
        );

        $updated = $this->repository->update($asset->id, $updatedData);

        $this->assertEquals($originalName . ' (REPOSITORIO ACTUALIZADO)', $updated->name);
    }

    public function test_it_can_paginate_filtered_inventory_assets(): void
    {
        $paginator = $this->repository->paginateFiltered(['perPage' => 5], 5);

        $this->assertNotNull($paginator);
        $this->assertLessThanOrEqual(5, $paginator->count());
    }

    public function test_it_can_get_summary_stats(): void
    {
        $stats = $this->repository->getSummaryStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('in_use', $stats);
        $this->assertArrayHasKey('in_stock', $stats);
        $this->assertIsInt($stats['total']);
    }
}
