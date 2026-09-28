<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UpsDevice;
use App\Models\UpsTelemetryHistory;
use App\Services\ClusterConfigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUpsController extends Controller
{
    /**
     * Muestra el panel interactivo de monitoreo de energía y telemetría de UPS.
     */
    public function index(Request $request): View
    {
        $device = UpsDevice::first();

        // Si no existe, inicializar con registro por defecto
        if (!$device) {
            $device = UpsDevice::create([
                'name' => 'UPS ZTG LV6KL - Sede Valle Seco',
                'model' => 'ZTG LV6KL 6kVA',
                'serial_number' => 'LV6KL-VS-01',
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

        // Histórico de las últimas 24 horas (con fallback a últimos 50 puntos si hay < 2 registros por Regla de Oro #3)
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

        // Cálculo de métricas adicionales
        $loadWatts = round((($device->load_percent ?? 0) / 100.0) * 6000);
        $estimatedRuntimeMinutes = $this->calculateEstimatedRuntime(
            $device->battery_percent ?? 100,
            $device->load_percent ?? 8
        );

        $outageDurationStr = null;
        if ($device->is_on_battery && $device->outage_since) {
            $outageDurationStr = Carbon::parse($device->outage_since)->diffForHumans(null, true);
        }

        // Preparar puntos para el gráfico Chart.js
        $chartLabels = [];
        $chartInputV = [];
        $chartOutputV = [];
        $chartLoadPct = [];
        $chartBatteryPct = [];
        $chartTemp = [];

        foreach ($histories as $h) {
            $chartLabels[] = Carbon::parse($h->recorded_at)->format('H:i:s');
            $chartInputV[] = (float) $h->input_voltage;
            $chartOutputV[] = (float) $h->output_voltage;
            $chartLoadPct[] = (int) $h->load_percent;
            $chartBatteryPct[] = (int) $h->battery_percent;
            $chartTemp[] = (float) $h->temperature_c;
        }

        return view('admin.ups.index', compact(
            'device',
            'histories',
            'loadWatts',
            'estimatedRuntimeMinutes',
            'outageDurationStr',
            'chartLabels',
            'chartInputV',
            'chartOutputV',
            'chartLoadPct',
            'chartBatteryPct',
            'chartTemp'
        ));
    }

    /**
     * Endpoint JSON para polling en tiempo real desde el dashboard.
     */
    public function live(): JsonResponse
    {
        $device = UpsDevice::first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Dispositivo UPS no configurado.',
            ], 404);
        }

        $loadWatts = round((($device->load_percent ?? 0) / 100.0) * 6000);
        $estimatedRuntimeMinutes = $this->calculateEstimatedRuntime(
            $device->battery_percent ?? 100,
            $device->load_percent ?? 8
        );

        $outageDurationStr = null;
        if ($device->is_on_battery && $device->outage_since) {
            $outageDurationStr = Carbon::parse($device->outage_since)->diffForHumans(null, true);
        }

        $lastSeenHuman = $device->last_seen_at
            ? Carbon::parse($device->last_seen_at)->diffForHumans()
            : 'Desconocido';

        return response()->json([
            'success' => true,
            'device' => $device,
            'computed' => [
                'load_watts' => $loadWatts,
                'estimated_runtime_minutes' => $estimatedRuntimeMinutes,
                'estimated_runtime_human' => $this->formatRuntime($estimatedRuntimeMinutes),
                'outage_duration' => $outageDurationStr,
                'last_seen_human' => $lastSeenHuman,
                'status_state' => $device->is_on_battery ? 'ON_BATTERY' : ($device->is_online ? 'NORMAL' : 'OFFLINE'),
            ],
        ]);
    }

    /**
     * Actualiza la configuración de alertas de Telegram para el UPS.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $device = UpsDevice::firstOrFail();

        $validated = $request->validate([
            'telegram_alert_enabled' => ['required', 'boolean'],
            'telegram_alert_target' => ['required', 'in:owner,group'],
        ]);

        $oldEnabled = $device->telegram_alert_enabled;
        $oldTarget = $device->telegram_alert_target;

        $device->update([
            'telegram_alert_enabled' => $validated['telegram_alert_enabled'],
            'telegram_alert_target' => $validated['telegram_alert_target'],
        ]);

        // Registrar en Auditoría del Sistema
        if (class_exists(AuditLog::class) && auth()->check()) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? 'Usuario',
                'user_email' => auth()->user()->email ?? '',
                'user_role' => auth()->user()->role ?? 'admin',
                'event' => 'updated',
                'module' => 'settings',
                'auditable_type' => UpsDevice::class,
                'auditable_id' => $device->id,
                'entity_name' => 'Supervisión UPS',
                'entity_label' => $device->name,
                'description' => "Configuración de alertas de UPS modificada. Alertas: " .
                    ($validated['telegram_alert_enabled'] ? 'Activadas' : 'Desactivadas') .
                    ", Destino: " . ($validated['telegram_alert_target'] === 'group' ? 'Grupo Corporativo' : 'Administrador Privado'),
                'old_values' => ['enabled' => $oldEnabled, 'target' => $oldTarget],
                'new_values' => ['enabled' => $validated['telegram_alert_enabled'], 'target' => $validated['telegram_alert_target']],
                'changed_fields' => ['telegram_alert_enabled', 'telegram_alert_target'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('admin.ups.index')->with('success', 'Configuración de alertas de UPS actualizada exitosamente.');
    }

    /**
     * Calcula la autonomía estimada en minutos para banco de 16 baterías AGM (192Vdc).
     */
    private function calculateEstimatedRuntime(int $batteryPercent, int $loadPercent): int
    {
        $loadPct = max(5, $loadPercent);
        $batPct = max(1, $batteryPercent);

        // Banco típico de 16x 12V 7.2Ah = ~1380 Wh utilizables
        // A carga del 8% (~480W), tiempo estimado ~80-100 min al 100% de batería.
        $baseRuntimeAtFullBat = 650.0 / $loadPct;
        $estMinutes = round(($batPct / 100.0) * $baseRuntimeAtFullBat);

        return (int) max(1, min(600, $estMinutes));
    }

    /**
     * Formatea minutos a cadena legible.
     */
    private function formatRuntime(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }
}
