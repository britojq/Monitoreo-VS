<?php

namespace Tests\Unit;

use App\Models\UpsDevice;
use App\Models\UpsTelemetryHistory;
use PHPUnit\Framework\TestCase;

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
}
