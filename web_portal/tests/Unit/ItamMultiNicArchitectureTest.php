<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InventoryAsset;
use App\Models\InventoryAssetNetworkInterface;
use App\Services\Glpi\GlpiHardwareDiffEngine;
use App\Services\Glpi\GlpiHardwareNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ItamMultiNicArchitectureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_multi_nic_model_and_relationship_with_inventory_asset(): void
    {
        $asset = InventoryAsset::create([
            'name' => 'SRV-MULTINIC-TEST',
            'type' => 'server',
            'status' => 'in_stock',
            'serial_number' => 'SN-NIC-TEST-' . uniqid(),
            'ip_address' => '192.168.1.190',
            'mac_address' => '00:11:22:AA:BB:CC',
        ]);

        // La interfaz primaria fue creada automáticamente por el hook
        $this->assertDatabaseHas('inventory_asset_network_interfaces', [
            'inventory_asset_id' => $asset->id,
            'ip_address' => '192.168.1.190',
            'mac_address' => '00:11:22:AA:BB:CC',
            'is_primary' => 1,
        ]);

        // Agregar una segunda interfaz (WiFi / OOB / VLAN)
        $nic2 = InventoryAssetNetworkInterface::create([
            'inventory_asset_id' => $asset->id,
            'interface_name' => 'wlan0',
            'mac_address' => '00:11:22:AA:BB:DD',
            'ip_address' => '192.168.10.45',
            'subnet_mask' => '255.255.255.0',
            'interface_type' => 'wifi',
            'status' => 'up',
            'is_primary' => false,
        ]);

        $asset->refresh();
        $this->assertCount(2, $asset->networkInterfaces);
        $this->assertNotNull($asset->primaryNetworkInterface);
        $this->assertEquals('00:11:22:AA:BB:CC', $asset->primaryNetworkInterface->mac_address);
        $this->assertEquals('wlan0 (192.168.10.45)', $nic2->display_label);
    }

    public function test_glpi_normalizer_extracts_multi_nic_and_scores_primary_interface(): void
    {
        $normalizer = new GlpiHardwareNormalizer();

        $rawParsed = [
            'content' => [
                'hardware' => [
                    'name' => 'SRV-CLUSTER-01',
                    'memory' => 16384,
                ],
                'bios' => [
                    'smanufacturer' => 'Dell Inc.',
                    'smodel' => 'PowerEdge R740',
                    'ssn' => 'CN77889900',
                ],
                'networks' => [
                    [
                        'description' => 'docker0',
                        'mac' => '02:42:AC:11:00:01',
                        'ipaddress' => '172.17.0.1',
                        'status' => 'up',
                    ],
                    [
                        'description' => 'eno1',
                        'mac' => 'B4:96:91:22:33:44',
                        'ipaddress' => '192.168.1.15',
                        'status' => 'up',
                    ],
                    [
                        'description' => 'eno2',
                        'mac' => 'B4:96:91:22:33:45',
                        'ipaddress' => '192.168.100.20',
                        'status' => 'up',
                    ],
                ],
            ]
        ];

        $norm = $normalizer->normalize($rawParsed, '192.168.1.15');

        $this->assertArrayHasKey('network_interfaces', $norm);
        $this->assertCount(3, $norm['network_interfaces']);

        // Backwards compatibility preservada
        $this->assertEquals('B4:96:91:22:33:44', $norm['mac_address']);
        $this->assertEquals('192.168.1.15', $norm['ip_address']);

        // La interfaz primaria debe ser eno1 (red corporativa 10.20.X)
        $primaryNics = array_filter($norm['network_interfaces'], fn($n) => $n['is_primary'] === true);
        $this->assertCount(1, $primaryNics);
        $primary = reset($primaryNics);
        $this->assertEquals('eno1', $primary['interface_name']);
        $this->assertEquals('B4:96:91:22:33:44', $primary['mac_address']);
        $this->assertEquals('192.168.1.15', $primary['ip_address']);
        $this->assertEquals('ethernet', $primary['interface_type']);
    }

    public function test_diff_engine_persists_and_updates_multi_nic_records(): void
    {
        $asset = InventoryAsset::create([
            'name' => 'PC-MULTI-TEST',
            'type' => 'desktop',
            'status' => 'in_stock',
            'serial_number' => 'SN-DIFF-' . uniqid(),
        ]);

        $normalizer = new GlpiHardwareNormalizer();
        $diffEngine = new GlpiHardwareDiffEngine($normalizer);

        $norm = [
            'network_interfaces' => [
                [
                    'interface_name' => 'eth0',
                    'mac_address' => '11:22:33:44:55:66',
                    'ip_address' => '192.168.1.99',
                    'subnet_mask' => '255.255.255.0',
                    'gateway' => '192.168.1.1',
                    'dns' => '192.168.1.2',
                    'interface_type' => 'ethernet',
                    'speed' => '1000 Mbps',
                    'status' => 'up',
                    'is_primary' => true,
                ],
                [
                    'interface_name' => 'eth1',
                    'mac_address' => '11:22:33:44:55:67',
                    'ip_address' => '192.168.5.10',
                    'subnet_mask' => '255.255.255.0',
                    'gateway' => null,
                    'dns' => null,
                    'interface_type' => 'ethernet',
                    'speed' => '1000 Mbps',
                    'status' => 'up',
                    'is_primary' => false,
                ]
            ]
        ];

        // 1. Sincronizar interfaces
        $diffEngine->syncNetworkInterfaces($asset, $norm, false);

        $this->assertDatabaseHas('inventory_asset_network_interfaces', [
            'inventory_asset_id' => $asset->id,
            'interface_name' => 'eth0',
            'mac_address' => '11:22:33:44:55:66',
            'ip_address' => '192.168.1.99',
            'is_primary' => 1,
        ]);

        $this->assertDatabaseHas('inventory_asset_network_interfaces', [
            'inventory_asset_id' => $asset->id,
            'interface_name' => 'eth1',
            'mac_address' => '11:22:33:44:55:67',
            'ip_address' => '192.168.5.10',
            'is_primary' => 0,
        ]);

        // 2. Modificar IP en eth1 y re-sincronizar
        $norm['network_interfaces'][1]['ip_address'] = '192.168.5.99';
        $diffEngine->syncNetworkInterfaces($asset, $norm, false);

        $this->assertDatabaseHas('inventory_asset_network_interfaces', [
            'inventory_asset_id' => $asset->id,
            'interface_name' => 'eth1',
            'ip_address' => '192.168.5.99',
        ]);

        // Verificar que se registró en el historial de componentes/interfaces
        $this->assertDatabaseHas('inventory_components_histories', [
            'inventory_asset_id' => $asset->id,
            'component_type' => 'network_interface',
            'action' => 'modified',
        ]);
    }
}
