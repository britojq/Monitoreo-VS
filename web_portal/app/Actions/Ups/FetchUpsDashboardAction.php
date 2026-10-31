<?php

declare(strict_types=1);

namespace App\Actions\Ups;

use App\Models\InventoryAsset;
use App\Models\UpsDevice;
use App\Models\UpsPowerEvent;
use App\Models\UpsTelemetryHistory;
use App\Services\Hardware\UpsHardwareService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FetchUpsDashboardAction
{
    public function __construct(
        protected UpsHardwareService $hardwareService
    ) {}

    public function getOrCreateDevice(): UpsDevice
    {
        $device = UpsDevice::first();
        if (!$device) {
            $device = UpsDevice::create([
                'name' => 'UPS ZTG LV6KL - Sede Valle Seco',
                'model' => 'ZTG LV6KL 6kVA',
                'serial_number' => 'UPS-DEMO-01',
                'serial_port' => '/dev/ttyS0',
                'baud_rate' => 2400,
                'rating_voltage' => 208.0,
                'rating_current' => 28.0,
                'rating_battery_voltage' => 192.0,
                'rating_frequency' => 60.0,
                'firmware_version' => 'R1.01.55',
                'input_voltage' => 224.8,
                'input_fault_voltage' => 0.0,
                'output_voltage' => 207.8,
                'load_percent' => 8,
                'frequency' => 60.0,
                'battery_voltage' => 2.25,
                'battery_percent' => 100,
                'temperature_c' => 43.0,
                'is_online' => true,
                'is_on_battery' => false,
                'is_battery_low' => false,
                'is_bypass' => false,
                'is_ups_failed' => false,
                'beeper_on' => true,
                'telegram_alert_enabled' => true,
                'telegram_alert_target' => 'owner',
                'last_alert_state' => 'NORMAL',
                'last_seen_at' => now(),
            ]);
        }
        return $device;
    }

    public function execute(Request $request): array
    {
        $device = $this->getOrCreateDevice();
        $since = now()->subHours(24);
        $histories = UpsTelemetryHistory::where('ups_device_id', $device->id)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at', 'asc')
            ->get();

        if ($histories->count() < 2) {
            $histories = UpsTelemetryHistory::where('ups_device_id', $device->id)
                ->orderBy('recorded_at', 'desc')
                ->take(50)
                ->get()
                ->reverse()
                ->values();
        }

        $totalHistoryCount = $histories->count();
        if ($totalHistoryCount > 120) {
            $step = (int) ceil($totalHistoryCount / 120);
            $sampled = collect();
            for ($i = 0; $i < $totalHistoryCount; $i += $step) {
                $sampled->push($histories[$i]);
            }
            if ($histories->isNotEmpty() && $sampled->last()?->id !== $histories->last()->id) {
                $sampled->push($histories->last());
            }
            $histories = $sampled;
        }

        $loadWatts = round((($device->load_percent ?? 0) / 100.0) * 6000);
        $estimatedRuntimeMinutes = $this->hardwareService->calculateEstimatedRuntime(
            $device->battery_percent ?? 100,
            $device->load_percent ?? 8
        );

        $outageDurationStr = ($device->is_on_battery && $device->outage_since)
            ? Carbon::parse($device->outage_since)->diffForHumans(null, true)
            : null;

        $chartLabels = [];
        $chartInputV = [];
        $chartOutputV = [];
        $chartOutputEquiposV = [];
        $chartLoadPct = [];
        $chartBatteryPct = [];
        $chartTemp = [];

        foreach ($histories as $h) {
            $chartLabels[] = Carbon::parse($h->recorded_at)->format('H:i:s');
            $chartInputV[] = (float) $h->input_voltage;
            $chartOutputV[] = (float) $h->output_voltage;
            $chartOutputEquiposV[] = (float) $h->output_voltage_equipos;
            $chartLoadPct[] = (int) $h->load_percent;
            $chartBatteryPct[] = (int) $h->battery_percent;
            $chartTemp[] = (float) $h->temperature_c;
        }

        $powerEvents = UpsPowerEvent::where('ups_device_id', $device->id)
            ->orderBy('started_at', 'desc')
            ->paginate(5, ['*'], 'events_page');

        $inventoryAssets = InventoryAsset::orderBy('name')->get(['id', 'name', 'ip_address', 'brand', 'model']);

        return [
            'device' => $device,
            'inventoryAssets' => $inventoryAssets,
            'outputVoltageEquipos' => $device->output_voltage_equipos,
            'histories' => $histories,
            'powerEvents' => $powerEvents,
            'loadWatts' => $loadWatts,
            'estimatedRuntimeMinutes' => $estimatedRuntimeMinutes,
            'outageDurationStr' => $outageDurationStr,
            'chartLabels' => $chartLabels,
            'chartInputV' => $chartInputV,
            'chartOutputV' => $chartOutputV,
            'chartOutputEquiposV' => $chartOutputEquiposV,
            'chartLoadPct' => $chartLoadPct,
            'chartBatteryPct' => $chartBatteryPct,
            'chartTemp' => $chartTemp,
        ];
    }

    public function getLiveData(): ?array
    {
        $device = UpsDevice::first();
        if (!$device) {
            return null;
        }

        $loadWatts = round((($device->load_percent ?? 0) / 100.0) * 6000);
        $estimatedRuntimeMinutes = $this->hardwareService->calculateEstimatedRuntime(
            $device->battery_percent ?? 100,
            $device->load_percent ?? 8
        );

        $outageDurationStr = ($device->is_on_battery && $device->outage_since)
            ? Carbon::parse($device->outage_since)->diffForHumans(null, true)
            : null;

        $lastSeenHuman = $device->last_seen_at
            ? Carbon::parse($device->last_seen_at)->diffForHumans()
            : 'Desconocido';

        return [
            'success' => true,
            'device' => $device,
            'computed' => [
                'load_watts' => $loadWatts,
                'estimated_runtime_minutes' => $estimatedRuntimeMinutes,
                'estimated_runtime_human' => $this->hardwareService->formatRuntime($estimatedRuntimeMinutes),
                'outage_duration' => $outageDurationStr,
                'last_seen_human' => $lastSeenHuman,
                'status_state' => $device->is_on_battery ? 'ON_BATTERY' : ($device->is_online ? 'NORMAL' : 'OFFLINE'),
                'output_voltage_equipos' => $device->output_voltage_equipos,
            ],
        ];
    }
}
