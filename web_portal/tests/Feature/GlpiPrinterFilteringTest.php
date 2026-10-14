<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredSite;
use App\Models\User;
use App\Services\Glpi\GlpiAssetSyncService;
use App\Services\Glpi\GlpiHardwareNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GlpiPrinterFilteringTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected MonitoredSite $site;
    protected GlpiAssetSyncService $syncService;
    protected GlpiHardwareNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_printer_test@test.local'],
            [
                'name' => 'Printer Tester',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'status' => 'active',
                'terms_accepted' => true,
            ]
        );

        $this->site = MonitoredSite::first() ?? MonitoredSite::create([
            'name' => 'Sede Prueba',
            'ip_range' => '10.20.23.0/24',
        ]);

        $this->syncService = app(GlpiAssetSyncService::class);
        $this->normalizer = app(GlpiHardwareNormalizer::class);
    }

    public function test_glpi_rejects_mdns_broadcast_queues_and_preserves_physical_usb_printer(): void
    {
        $uniqueSerial = 'TEST-PC-' . uniqid();

        $asset = InventoryAsset::create([
            'name' => 'PC-PRINTER-TEST',
            'serial_number' => $uniqueSerial,
            'asset_tag' => 'TAG-' . uniqid(),
            'type' => 'desktop',
            'status' => 'assigned',
            'brand' => 'LENOVO',
            'model' => 'ThinkCentre M910s',
            'monitored_site_id' => $this->site->id,
            'ip_address' => '10.20.23.199',
        ]);

        // Simular que existían colas fantasmas previas
        InventoryPeripheral::create([
            'asset_tag' => 'PRN-GHOST-1',
            'type' => 'printer',
            'brand' => 'Canon',
            'model' => 'Canon_MF270_Series_35_f7_73',
            'serial_number' => null,
            'status' => 'assigned',
            'current_asset_id' => $asset->id,
            'notes' => 'Impresora detectada por GLPI [Serial no reportado]',
        ]);

        // Payload con colas mDNS remotas y una impresora física USB con serial
        $rawContent = [
            'hardware' => [
                'name' => 'PC-PRINTER-TEST',
                'uuid' => 'UUID-' . $uniqueSerial,
            ],
            'bios' => [
                'ssn' => $uniqueSerial,
            ],
            'printers' => [
                [
                    'name' => 'Canon_MF270_Series_35_f7_73',
                    'driver' => 'Canon MF270 Series',
                    'port' => 'Canon_MF270_Series_35_f7_73',
                ],
                [
                    'name' => 'Canon_MF270_Series_36_0f_e3',
                    'driver' => 'Canon MF270 Series',
                    'port' => 'Canon_MF270_Series_36_0f_e3',
                ],
                [
                    'name' => 'LBP6030-6040-6018L',
                    'driver' => 'Canon LBP6030/6040/6018L',
                    'port' => 'usb://Canon/LBP6030/6040/6018L?serial=CANONSERIAL123',
                    'serial' => 'CANONSERIAL123',
                ],
            ],
        ];

        $norm = $this->normalizer->normalize($rawContent, '10.20.23.199');
        $result = $this->syncService->sync($norm);

        $this->assertEquals($asset->id, $result['asset']->id);

        $peripherals = InventoryPeripheral::where('current_asset_id', $asset->id)
            ->where('type', 'printer')
            ->get();

        // Debe haber EXACTAMENTE 1 impresora (la USB con serial) y cero colas mDNS fantasmas
        $this->assertCount(1, $peripherals);
        $this->assertEquals('CANONSERIAL123', $peripherals->first()->serial_number);
        $this->assertEquals('LBP6030-6040-6018L', $peripherals->first()->model);
    }
}
