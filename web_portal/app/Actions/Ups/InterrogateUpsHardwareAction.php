<?php

declare(strict_types=1);

namespace App\Actions\Ups;

use App\Models\UpsDevice;
use App\Services\Hardware\UpsHardwareService;

class InterrogateUpsHardwareAction
{
    public function __construct(
        protected UpsHardwareService $hardwareService
    ) {}

    public function interrogate(?string $port = null, ?int $baud = null): array
    {
        $device = UpsDevice::first();
        $targetPort = !empty($port) ? $port : ($device?->serial_port ?? '/dev/ttyS0');
        $targetBaud = $baud && $baud > 0 ? $baud : ($device?->baud_rate ?? 2400);

        return $this->hardwareService->interrogateHardware($targetPort, $targetBaud);
    }

    public function listPorts(): array
    {
        $device = UpsDevice::first();
        return $this->hardwareService->listAvailablePorts($device?->serial_port, $device?->baud_rate);
    }

    public function testSerial(?string $port = null, ?int $baud = null): array
    {
        $device = UpsDevice::first();
        $targetPort = !empty($port) ? $port : ($device?->serial_port ?? '/dev/ttyS0');
        $targetBaud = $baud && $baud > 0 ? $baud : ($device?->baud_rate ?? 2400);

        return $this->hardwareService->testSerial($targetPort, $targetBaud);
    }

    public function scanPorts(): array
    {
        return $this->hardwareService->scanPorts();
    }
}
