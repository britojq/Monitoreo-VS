<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUpsSettingsRequest;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\UpsDevice;
use App\Models\UpsPowerEvent;
use App\Models\UpsTelemetryHistory;
use App\Services\Hardware\UpsHardwareService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador administrativo de supervisión y telemetría de UPS.
 * Diseñado bajo arquitectura Slim Controller (Principio S y D).
 * La comunicación de hardware y procesos del sistema se delega a UpsHardwareService.
 */
class AdminUpsController extends Controller
{
    public function __construct(
        protected UpsHardwareService $hardwareService
    ) {}

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

        // Muestreo Inteligente (Downsampling a máx 120 puntos para evitar saturar el hilo UI del navegador)
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

        // Cálculo de métricas adicionales delegando a UpsHardwareService
        $loadWatts = round((($device->load_percent ?? 0) / 100.0) * 6000);
        $estimatedRuntimeMinutes = $this->calculateEstimatedRuntime(
            $device->battery_percent ?? 100,
            $device->load_percent ?? 8
        );

        $outageDurationStr = null;
        if ($device->is_on_battery && $device->outage_since) {
            $outageDurationStr = Carbon::parse($device->outage_since)->diffForHumans(null, true);
        }

        $outputVoltageEquipos = $device->output_voltage_equipos;

        // Preparar puntos para el gráfico Chart.js
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

        // Últimos eventos eléctricos (micro-fluctuaciones y cortes) paginados a 5 ítems
        $powerEvents = UpsPowerEvent::where('ups_device_id', $device->id)
            ->orderBy('started_at', 'desc')
            ->paginate(5, ['*'], 'events_page');

        // Activos de inventario para vinculación ITAM (Regla de Oro #8)
        $inventoryAssets = InventoryAsset::orderBy('name')->get(['id', 'name', 'ip_address', 'brand', 'model']);

        return view('admin.ups.index', compact(
            'device',
            'inventoryAssets',
            'outputVoltageEquipos',
            'histories',
            'powerEvents',
            'loadWatts',
            'estimatedRuntimeMinutes',
            'outageDurationStr',
            'chartLabels',
            'chartInputV',
            'chartOutputV',
            'chartOutputEquiposV',
            'chartLoadPct',
            'chartBatteryPct',
            'chartTemp'
        ));
    }

    /**
     * Endpoint JSON para polling en tiempo real desde el dashboard.
     * Si un usuario navega directamente en el navegador (Accept: text/html), se redirige a la vista completa /admin/ups (Error #6).
     */
    public function live(Request $request): JsonResponse|RedirectResponse
    {
        if (!$request->expectsJson() && !$request->ajax() && !str_contains($request->header('Accept', ''), 'application/json')) {
            return redirect()->route('admin.ups.index');
        }

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
                'output_voltage_equipos' => $device->output_voltage_equipos,
            ],
        ]);
    }

    /**
     * Actualiza la configuración y metadatos del UPS, sincronizando en cascada con el inventario ITAM.
     */
    public function updateSettings(UpdateUpsSettingsRequest $request): RedirectResponse|JsonResponse
    {
        $device = UpsDevice::firstOrFail();
        $validated = $request->validated();

        $oldEnabled = $device->telegram_alert_enabled;
        $oldTarget = $device->telegram_alert_target;
        $oldPort = $device->serial_port;
        $oldBaud = $device->baud_rate;
        $oldName = $device->name;
        $oldModel = $device->model;
        $oldSerial = $device->serial_number;

        $updates = [
            'telegram_alert_enabled' => $validated['telegram_alert_enabled'],
            'telegram_alert_target' => $validated['telegram_alert_target'],
        ];

        if (array_key_exists('name', $validated) && !empty($validated['name'])) {
            $updates['name'] = trim($validated['name']);
        }
        if (array_key_exists('model', $validated) && !empty($validated['model'])) {
            $updates['model'] = trim($validated['model']);
        }
        if (array_key_exists('serial_number', $validated)) {
            $updates['serial_number'] = !empty($validated['serial_number']) ? trim($validated['serial_number']) : null;
        }
        if (!empty($validated['rating_voltage'])) {
            $updates['rating_voltage'] = (float) $validated['rating_voltage'];
        }
        if (!empty($validated['rating_battery_voltage'])) {
            $updates['rating_battery_voltage'] = (float) $validated['rating_battery_voltage'];
        }
        if (array_key_exists('inventory_asset_id', $validated)) {
            $updates['inventory_asset_id'] = $validated['inventory_asset_id'] ? (int) $validated['inventory_asset_id'] : null;
        }

        if (!empty($validated['serial_port'])) {
            $updates['serial_port'] = trim($validated['serial_port']);
        }
        if (!empty($validated['baud_rate'])) {
            $updates['baud_rate'] = (int) $validated['baud_rate'];
        }

        $device->update($updates);

        // Sincronización en Cascada hacia el censo ITAM (Regla de Oro #8)
        $syncedPeripheral = $device->syncToInventoryPeripheral();

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
                'description' => "Identidad y configuración de UPS actualizada. Modelo: {$device->model}, Puerto: {$device->serial_port} @{$device->baud_rate} bps, Sincronizado a ITAM: " . ($syncedPeripheral ? 'Sí (' . $syncedPeripheral->asset_tag . ')' : 'No'),
                'old_values' => ['name' => $oldName, 'model' => $oldModel, 'serial' => $oldSerial, 'port' => $oldPort, 'baud' => $oldBaud, 'enabled' => $oldEnabled, 'target' => $oldTarget],
                'new_values' => ['name' => $device->name, 'model' => $device->model, 'serial' => $device->serial_number, 'port' => $device->serial_port, 'baud' => $device->baud_rate, 'enabled' => $device->telegram_alert_enabled, 'target' => $device->telegram_alert_target],
                'changed_fields' => array_keys($updates),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Identidad y configuración de UPS actualizada y sincronizada con éxito al inventario ITAM.',
                'name' => $device->name,
                'model' => $device->model,
                'serial_number' => $device->serial_number,
                'serial_port' => $device->serial_port,
                'baud_rate' => $device->baud_rate,
                'rating_voltage' => $device->rating_voltage,
                'rating_battery_voltage' => $device->rating_battery_voltage,
                'telegram_alert_enabled' => (bool) $device->telegram_alert_enabled,
                'telegram_alert_target' => $device->telegram_alert_target,
            ]);
        }

        return redirect()->route('admin.ups.index')->with('success', 'Identidad y configuración de UPS actualizada y sincronizada con éxito.');
    }

    /**
     * Interroga en vivo al hardware del UPS enviando instrucciones Megatec Q1, I y F.
     */
    public function interrogateHardware(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->isAdmin() && !(method_exists($user, 'hasPermission') && $user->hasPermission('ups.manage')))) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado.'], 403);
        }

        $port = trim((string) $request->input('serial_port', ''));
        $baud = (int) $request->input('baud_rate', 2400);

        if (empty($port)) {
            $device = UpsDevice::first();
            $port = $device?->serial_port ?? '/dev/ttyS0';
            $baud = $device?->baud_rate ?? 2400;
        }

        $result = $this->hardwareService->interrogateHardware($port, $baud);

        return response()->json($result);
    }

    /**
     * Retorna la lista de puertos seriales físicos y adaptadores USB detectados en el servidor.
     */
    public function listPorts(): JsonResponse
    {
        $device = UpsDevice::first();
        $result = $this->hardwareService->listAvailablePorts($device?->serial_port, $device?->baud_rate);

        return response()->json($result);
    }

    /**
     * Prueba comunicación serial en vivo hacia el UPS con trama Megatec Q1.
     */
    public function testSerial(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->isAdmin() && !(method_exists($user, 'hasPermission') && $user->hasPermission('ups.manage')))) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado.'], 403);
        }

        $port = trim((string) $request->input('serial_port', ''));
        $baud = (int) $request->input('baud_rate', 2400);

        if (empty($port)) {
            $device = UpsDevice::first();
            $port = $device?->serial_port ?? '/dev/ttyS0';
            $baud = $device?->baud_rate ?? 2400;
        }

        $result = $this->hardwareService->testSerial($port, $baud);

        return response()->json($result);
    }

    /**
     * Ejecuta escaneo completo de todos los puertos disponibles buscando el UPS.
     */
    public function scanPorts(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || (!$user->isAdmin() && !(method_exists($user, 'hasPermission') && $user->hasPermission('ups.manage')))) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado.'], 403);
        }

        $result = $this->hardwareService->scanPorts();

        return response()->json($result);
    }

    /**
     * Calcula la autonomía estimada en minutos para banco de 16 baterías AGM (192Vdc).
     */
    public function calculateEstimatedRuntime(int $batteryPercent, int $loadPercent): int
    {
        return $this->hardwareService->calculateEstimatedRuntime($batteryPercent, $loadPercent);
    }

    /**
     * Formatea minutos a cadena legible.
     */
    public function formatRuntime(int $minutes): string
    {
        return $this->hardwareService->formatRuntime($minutes);
    }
}
