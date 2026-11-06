<?php

namespace Tests\Unit;

use App\Models\UpsDevice;
use App\Models\UpsTelemetryHistory;
use Tests\TestCase;

class UpsDeviceTest extends TestCase
{
    /**
     * Prueba el cálculo del voltaje de salida a equipos (110V nominal) desde 208V.
     */
    public function test_output_voltage_equipos_calculation(): void
    {
        $device = new UpsDevice();
        $device->output_voltage = 208.0;

        $this->assertEquals(110.0, $device->output_voltage_equipos);

        $device->output_voltage = 207.5;
        $this->assertEquals(109.7, $device->output_voltage_equipos);

        $device->output_voltage = 120.0;
        $this->assertEquals(120.0, $device->output_voltage_equipos);
    }

    /**
     * Prueba el cálculo del voltaje en el historial de telemetría.
     */
    public function test_history_output_voltage_equipos(): void
    {
        $history = new UpsTelemetryHistory();
        $history->output_voltage = 208.0;

        $this->assertEquals(110.0, $history->output_voltage_equipos);
    }

    /**
     * Prueba las etiquetas humanas y cálculo de duración de UpsPowerEvent.
     */
    public function test_ups_power_event_labels_and_durations(): void
    {
        $eventFluct = new \App\Models\UpsPowerEvent();
        $eventFluct->event_type = 'fluctuation';
        $eventFluct->duration_seconds = 9;
        $this->assertEquals('Micro-Fluctuación de Voltaje (≤45s)', $eventFluct->type_label);
        $this->assertEquals('9 seg', $eventFluct->duration_label);

        $eventOutage = new \App\Models\UpsPowerEvent();
        $eventOutage->event_type = 'outage';
        $eventOutage->duration_seconds = 75;
        $this->assertEquals('Falla de Servicio Eléctrico (>45s)', $eventOutage->type_label);
        $this->assertEquals('1 min 15 seg', $eventOutage->duration_label);

        $eventLong = new \App\Models\UpsPowerEvent();
        $eventLong->duration_seconds = 3600;
        $this->assertEquals('1 h 0 min', $eventLong->duration_label);
    }

    /**
     * Prueba la relación hasMany powerEvents en UpsDevice.
     */
    public function test_ups_device_power_events_relation(): void
    {
        $device = new UpsDevice();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $device->powerEvents());
    }
}

