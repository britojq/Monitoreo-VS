<?php

namespace Tests\Feature;

use App\Models\UpsDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UpsSerialManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected UpsDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
        $this->device = UpsDevice::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'UPS ZTG LV6KL - Test',
                'model' => 'ZTG LV6KL 6kVA',
                'serial_port' => '/dev/ttyS0',
                'baud_rate' => 2400,
                'telegram_alert_enabled' => true,
                'telegram_alert_target' => 'owner',
            ]
        );
    }

    public function test_ups_index_page_is_accessible(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ups.index'));

        $response->assertStatus(200);
        $response->assertSee('Comunicación Serial (RS-232 / USB)');
        $response->assertSee('Probar Comunicación');
        $response->assertSee('Escanear y Autodetectar');
    }

    public function test_ups_list_ports_returns_json(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.ups.ports'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'ports',
            'current_port',
            'current_baud',
        ]);
        $this->assertTrue($response->json('success'));
    }

    public function test_ups_settings_can_update_serial_port_and_baud_rate(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.ups.settings'), [
                'telegram_alert_enabled' => 1,
                'telegram_alert_target' => 'owner',
                'serial_port' => '/dev/ttyUSB0',
                'baud_rate' => 9600,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'serial_port' => '/dev/ttyUSB0',
            'baud_rate' => 9600,
        ]);

        $this->assertDatabaseHas('ups_devices', [
            'id' => $this->device->id,
            'serial_port' => '/dev/ttyUSB0',
            'baud_rate' => 9600,
        ]);
    }

    public function test_ups_test_serial_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.ups.test-serial'), [
                'serial_port' => '/dev/ttyS0',
                'baud_rate' => 2400,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'port',
            'baud',
            'message',
            'raw_output',
        ]);
    }

    public function test_ups_scan_ports_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.ups.scan-ports'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'raw_output',
        ]);
    }

    public function test_ups_settings_can_update_identity_and_syncs_to_inventory_peripheral(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.ups.settings'), [
                'name' => 'UPS Principal Server Rack',
                'model' => 'Forza EOS 6kVA',
                'serial_number' => 'EOS-6000-TEST',
                'rating_voltage' => 208.0,
                'rating_battery_voltage' => 192.0,
                'telegram_alert_enabled' => 1,
                'telegram_alert_target' => 'owner',
                'serial_port' => '/dev/ttyS0',
                'baud_rate' => 2400,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'name' => 'UPS Principal Server Rack',
            'model' => 'Forza EOS 6kVA',
            'serial_number' => 'EOS-6000-TEST',
        ]);

        $this->assertDatabaseHas('ups_devices', [
            'id' => $this->device->id,
            'name' => 'UPS Principal Server Rack',
            'model' => 'Forza EOS 6kVA',
            'serial_number' => 'EOS-6000-TEST',
        ]);

        // Verificar Regla de Oro #8: Sincronización en cascada a inventory_peripherals
        if ($this->device->inventory_asset_id) {
            $this->assertDatabaseHas('inventory_peripherals', [
                'current_asset_id' => $this->device->inventory_asset_id,
                'type' => 'ups',
                'brand' => 'Forza',
                'serial_number' => 'EOS-6000-TEST',
            ]);
        }
    }

    public function test_ups_interrogate_hardware_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.ups.interrogate'), [
                'serial_port' => '/dev/ttyS0',
                'baud_rate' => 2400,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'port',
            'baud',
            'message',
        ]);
    }
}
