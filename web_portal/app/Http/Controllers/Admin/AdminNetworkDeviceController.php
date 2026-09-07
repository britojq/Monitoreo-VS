<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoringSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminNetworkDeviceController extends Controller
{
    /**
     * Muestra el listado de dispositivos de red Valle Seco.
     * Accesible para Operadores y Administradores.
     */
    public function index(): View
    {
        $devices = MonitoredNetworkDevice::orderBy('device_number')->get();
        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotDevices = ($latestSnapshot && isset($latestSnapshot->payload_json['network_devices']))
            ? collect($latestSnapshot->payload_json['network_devices'])->keyBy('id')
            : collect();

        return view('admin.devices.index', compact('devices', 'snapshotDevices'));
    }

    /**
     * Actualiza la información técnica y de red de un dispositivo.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function update(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden modificar los dispositivos.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'string', 'max:255'],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => ['required', 'string', Rule::in(['TELNET', 'WEB', 'VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['access_type'] = strtoupper(trim($validated['access_type']));
        if (empty($validated['access_port'])) {
            if ($validated['access_type'] === 'TELNET') {
                $validated['access_port'] = 23;
            } elseif ($validated['access_type'] === 'WEB') {
                $validated['access_port'] = 80;
            } elseif ($validated['access_type'] === 'VNC') {
                $validated['access_port'] = 5900;
            } else {
                $validated['access_port'] = null;
            }
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $device->update($validated);

        // Disparar sincronización con config/monitoreo.conf
        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$device->name}] actualizado y sincronizado exitosamente.");
    }

    /**
     * Activa o desactiva el monitoreo de un dispositivo.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function toggle(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden modificar el estado del dispositivo.');
        }

        $device->is_active = !$device->is_active;
        $device->save();

        app(SyncController::class)->exportToConfigFiles();

        $status = $device->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Dispositivo [{$device->name}] {$status} exitosamente.");
    }

    /**
     * Retorna datos históricos para gráficos y telemetría.
     * Accesible para Operadores y Administradores.
     */
    public function history(Request $request, MonitoredNetworkDevice $device): JsonResponse
    {
        $range = $request->query('range', '24h');
        $query = $device->histories()->reorder('checked_at', 'asc');

        switch ($range) {
            case '6h':
                $query->where('checked_at', '>=', now()->subHours(6));
                break;
            case '7d':
                $query->where('checked_at', '>=', now()->subDays(7));
                break;
            case '30d':
                $query->where('checked_at', '>=', now()->subDays(30));
                break;
            case '24h':
            default:
                $query->where('checked_at', '>=', now()->subHours(24));
                break;
        }

        $records = $query->get();

        $totalChecks = $records->count();
        $upChecks = $records->where('is_up', true)->count();
        $downChecks = $totalChecks - $upChecks;
        $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : 0.0;

        $upRecords = $records->where('is_up', true);
        $avgLatency = $upRecords->count() > 0 ? round($upRecords->avg('latency_ms'), 2) : 0.0;
        $maxLatency = $upRecords->count() > 0 ? round($upRecords->max('latency_ms'), 2) : 0.0;
        $minLatency = $upRecords->count() > 0 ? round($upRecords->min('latency_ms'), 2) : 0.0;

        $labels = [];
        $latencies = [];
        $statuses = [];
        $points = [];

        foreach ($records as $r) {
            $formattedTime = ($range == '7d' || $range == '30d')
                ? $r->checked_at->format('d/m H:i')
                : $r->checked_at->format('H:i');

            $labels[] = $formattedTime;
            $latencies[] = $r->is_up ? (float) $r->latency_ms : 0.0;
            $statuses[] = $r->is_up ? 1 : 0;
            $points[] = [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'is_up' => $r->is_up,
                'latency_ms' => (float) $r->latency_ms,
                'status_message' => $r->status_message,
            ];
        }

        $incidents = $records->where('is_up', false)->sortByDesc('checked_at')->values()->map(function($r) {
            return [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'status_message' => $r->status_message ?: 'Timeout / Sin respuesta',
            ];
        });

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'device_number' => $device->device_number,
                'name' => $device->name,
                'ip' => $device->ip,
                'mac' => $device->mac,
                'vendor_data' => $device->vendor_data,
                'model' => $device->model,
                'serial' => $device->serial,
                'ports' => $device->ports,
                'notes' => $device->notes,
                'access_type' => $device->access_type,
                'access_port' => $device->access_port,
                'is_active' => $device->is_active,
            ],
            'range' => $range,
            'stats' => [
                'total_checks' => $totalChecks,
                'up_checks' => $upChecks,
                'down_checks' => $downChecks,
                'uptime_percentage' => $uptimePct,
                'avg_latency' => $avgLatency,
                'max_latency' => $maxLatency,
                'min_latency' => $minLatency,
                'last_check' => $records->last() ? $records->last()->checked_at->format('Y-m-d H:i:s') : null,
                'last_check_human' => $records->last() ? $records->last()->checked_at->diffForHumans() : null,
            ],
            'labels' => $labels,
            'latencies' => $latencies,
            'statuses' => $statuses,
            'points' => $points,
            'incidents' => $incidents,
        ]);
    }
}
