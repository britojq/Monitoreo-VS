<?php

namespace Tests\Unit;

use App\Services\Glpi\GlpiHardwareNormalizer;
use App\Services\Glpi\GlpiPayloadDecoder;
use App\Services\GlpiInventoryService;
use Tests\TestCase;

class GlpiPipelineArchitectureTest extends TestCase
{
    public function test_decoder_parses_json_payload(): void
    {
        $decoder = new GlpiPayloadDecoder();
        $raw = json_encode([
            'action' => 'inventory',
            'content' => [
                'hardware' => ['name' => 'PC-UNIT-TEST'],
                'bios' => ['ssn' => 'SN998877'],
            ],
        ]);

        $result = $decoder->decode($raw);
        $this->assertEquals('json', $result['format']);
        $this->assertFalse($result['is_prolog']);
        $this->assertEquals('PC-UNIT-TEST', $result['parsed']['content']['hardware']['name']);
    }

    public function test_decoder_identifies_prolog_handshake(): void
    {
        $decoder = new GlpiPayloadDecoder();
        $raw = json_encode(['action' => 'getConfig']);

        $result = $decoder->decode($raw);
        $this->assertTrue($result['is_prolog']);
    }

    public function test_normalizer_homogenizes_hardware_and_filters_generic_serial(): void
    {
        $normalizer = new GlpiHardwareNormalizer();
        $this->assertTrue($normalizer->isGenericSerial('To Be Filled By O.E.M.'));
        $this->assertTrue($normalizer->isGenericSerial('00000000'));
        $this->assertFalse($normalizer->isGenericSerial('DELL1234567'));

        $rawParsed = [
            'content' => [
                'hardware' => [
                    'name' => 'SRV-TEST',
                    'memory' => 8192,
                ],
                'bios' => [
                    'smanufacturer' => 'Dell Inc.',
                    'smodel' => 'PowerEdge R640',
                    'ssn' => 'To Be Filled By O.E.M.', // Genérico, debe ser filtrado a null
                ],
                'networks' => [
                    [
                        'mac' => '00:11:22:33:44:55',
                        'ipaddress' => '192.168.1.88',
                        'status' => 'up',
                        'description' => 'eth0',
                    ]
                ],
            ]
        ];

        $norm = $normalizer->normalize($rawParsed, '192.168.1.88');
        $this->assertEquals('SRV-TEST', $norm['name']);
        $this->assertEquals('Dell Inc.', $norm['brand']);
        $this->assertEquals('PowerEdge R640', $norm['model']);
        $this->assertNull($norm['serial_number']);
        $this->assertEquals('00:11:22:33:44:55', $norm['mac_address']);
        $this->assertEquals('192.168.1.88', $norm['ip_address']);
    }

    public function test_facade_exposes_submodules_cleanly(): void
    {
        $service = app(GlpiInventoryService::class);
        $this->assertInstanceOf(GlpiPayloadDecoder::class, $service->getDecoder());
        $this->assertInstanceOf(GlpiHardwareNormalizer::class, $service->getNormalizer());
    }
}
