<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\MonitoringSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminNetworkDeviceController extends Controller
{
    /**
     * Muestra el listado centralizado de dispositivos de red de todas las sedes.
     * Accesible para Operadores y Administradores.
     */
    public function index(Request $request): View
    {
        $siteFilter = $request->query('site_id');
        $query = MonitoredNetworkDevice::with('site')->orderBy('monitored_site_id')->orderBy('device_number');

        if (!empty($siteFilter)) {
            $query->where('monitored_site_id', $siteFilter);
        }

        $totalCount = (clone $query)->count();
        $activeCount = (clone $query)->where('is_active', true)->count();
        $inactiveCount = (clone $query)->where('is_active', false)->count();

        $devices = $query->paginate(15)->withQueryString();
        $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();

        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotDevices = ($latestSnapshot && isset($latestSnapshot->payload_json['network_devices']))
            ? collect($latestSnapshot->payload_json['network_devices'])->keyBy('id')
            : collect();

        return view('admin.devices.index', compact('devices', 'sites', 'siteFilter', 'snapshotDevices', 'totalCount', 'activeCount', 'inactiveCount'));
    }

    /**
     * Registra un nuevo dispositivo de red y lo asigna a una sede.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function store(Request $request): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden agregar dispositivos.');
        }

        $validated = $request->validate([
            'monitored_site_id' => ['required', 'exists:monitored_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'string', 'max:255', 'unique:monitored_network_devices,ip'],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => ['required', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['access_type'] = strtoupper(trim($validated['access_type']));
        if (empty($validated['access_port'])) {
            if ($validated['access_type'] === 'SSH') {
                $validated['access_port'] = 22;
            } elseif ($validated['access_type'] === 'TELNET') {
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
        $nextNum = (MonitoredNetworkDevice::where('monitored_site_id', $validated['monitored_site_id'])->max('device_number') ?? 0) + 1;
        $validated['device_number'] = $nextNum;
        $validated['sort_order'] = MonitoredNetworkDevice::count() + 1;

        $device = MonitoredNetworkDevice::create($validated);

        // Sincronizar espejo en monitored_site_devices para que sea visible en admin/sites
        $nextSiteDevNum = (MonitoredSiteDevice::where('monitored_site_id', $validated['monitored_site_id'])->max('device_number') ?? 0) + 1;
        MonitoredSiteDevice::updateOrCreate(
            ['monitored_site_id' => $validated['monitored_site_id'], 'ip' => $validated['ip']],
            [
                'device_number' => $nextSiteDevNum,
                'name' => $validated['name'],
                'mac' => $validated['mac'] ?? null,
                'vendor_data' => $validated['vendor_data'] ?? null,
                'access_type' => $validated['access_type'],
                'access_port' => $validated['access_port'],
                'model' => $validated['model'] ?? null,
                'serial' => $validated['serial'] ?? null,
                'ports' => $validated['ports'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => $validated['is_active'],
            ]
        );

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$device->name}] creado y asignado exitosamente.");
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
            'monitored_site_id' => ['nullable', 'exists:monitored_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'string', 'max:255', Rule::unique('monitored_network_devices', 'ip')->ignore($device->id)],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => ['required', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['access_type'] = strtoupper(trim($validated['access_type']));
        if (empty($validated['access_port'])) {
            if ($validated['access_type'] === 'SSH') {
                $validated['access_port'] = 22;
            } elseif ($validated['access_type'] === 'TELNET') {
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
        $oldIp = $device->ip;

        $device->update($validated);

        // Sincronizar en monitored_site_devices
        $siteId = $validated['monitored_site_id'] ?? $device->monitored_site_id;
        if ($siteId) {
            $siteDev = MonitoredSiteDevice::where('ip', $oldIp)->first();
            if ($siteDev) {
                $siteDev->update([
                    'monitored_site_id' => $siteId,
                    'name' => $validated['name'],
                    'ip' => $validated['ip'],
                    'mac' => $validated['mac'] ?? null,
                    'vendor_data' => $validated['vendor_data'] ?? null,
                    'access_type' => $validated['access_type'],
                    'access_port' => $validated['access_port'],
                    'model' => $validated['model'] ?? null,
                    'serial' => $validated['serial'] ?? null,
                    'ports' => $validated['ports'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'is_active' => $validated['is_active'],
                ]);
            } else {
                $nextSiteDevNum = (MonitoredSiteDevice::where('monitored_site_id', $siteId)->max('device_number') ?? 0) + 1;
                MonitoredSiteDevice::create([
                    'monitored_site_id' => $siteId,
                    'device_number' => $nextSiteDevNum,
                    'name' => $validated['name'],
                    'ip' => $validated['ip'],
                    'mac' => $validated['mac'] ?? null,
                    'vendor_data' => $validated['vendor_data'] ?? null,
                    'access_type' => $validated['access_type'],
                    'access_port' => $validated['access_port'],
                    'model' => $validated['model'] ?? null,
                    'serial' => $validated['serial'] ?? null,
                    'ports' => $validated['ports'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'is_active' => $validated['is_active'],
                ]);
            }
        }

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$device->name}] actualizado y sincronizado exitosamente.");
    }

    /**
     * Elimina un dispositivo de red y su reflejo en la sede.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function destroy(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden eliminar dispositivos.');
        }

        $name = $device->name;
        $ip = $device->ip;

        // Eliminar también en monitored_site_devices
        MonitoredSiteDevice::where('ip', $ip)->delete();

        $device->delete();

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$name}] eliminado exitosamente.");
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

        // Sincronizar estado en monitored_site_devices
        MonitoredSiteDevice::where('ip', $device->ip)->update(['is_active' => $device->is_active]);

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
