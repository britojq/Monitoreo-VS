<?php

namespace Tests\Feature;

use App\Models\UpsDevice;
use App\Models\UpsPowerEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsAdminTest extends TestCase
{
    public function test_admin_ups_page_renders_with_power_events(): void
    {
        $user = User::where('role', 'admin')->first();
        if (!$user) {
            $user = User::factory()->create(['role' => 'admin']);
        }

        $device = UpsDevice::first();
        if (!$device) {
            $device = UpsDevice::create([
                'name' => 'UPS ZTG LV6KL',
                'model' => '6kVA',
                'serial_port' => '/dev/ttyS0',
                'baud_rate' => 2400,
            ]);
        }

        $event = UpsPowerEvent::create([
            'ups_device_id' => $device->id,
            'event_type' => 'fluctuation',
            'started_at' => now()->subSeconds(30),
            'ended_at' => now()->subSeconds(21),
            'duration_seconds' => 9,
            'input_voltage' => 220.0,
            'status' => 'resolved',
            'notified_group' => false,
            'notified_target' => 'owner',
            'description' => 'Micro-fluctuación de prueba superada',
        ]);

        $response = $this->actingAs($user)->get('/admin/ups');
        $response->assertStatus(200);
        $response->assertSee('Registro de Micro-Fluctuaciones', false);
        $response->assertSee('Micro-Fluctuación (≤45s)');
        $response->assertSee('9 seg');

        // Limpiar evento de prueba
        $event->delete();
    }
}
