<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoringSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSiteController extends Controller
{
    public function index(): View
    {
        $sites = MonitoredSite::with('devices')->orderBy('sort_order')->get();
        $networkDevices = MonitoredNetworkDevice::all()->keyBy('ip');
        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotDevices = ($latestSnapshot && isset($latestSnapshot->payload_json['network_devices']))
            ? collect($latestSnapshot->payload_json['network_devices'])->keyBy('ip')
            : collect();

        return view('admin.sites.index', compact('sites', 'networkDevices', 'snapshotDevices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['nullable', 'string', 'max:255'],
            'phone_1' => ['nullable', 'string', 'max:100'],
            'phone_2' => ['nullable', 'string', 'max:100'],
            'phone_3' => ['nullable', 'string', 'max:100'],
            'phone_4' => ['nullable', 'string', 'max:100'],
            'phone_5' => ['nullable', 'string', 'max:100'],
            'phone_6' => ['nullable', 'string', 'max:100'],
            'phone_7' => ['nullable', 'string', 'max:100'],
            'phone_8' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'normal_state_msg' => ['nullable', 'string', 'max:255'],
            'error_state_msg' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (empty($validated['letter'])) {
            $nextId = (MonitoredSite::max('id') ?? 0) + 1;
            $validated['letter'] = "ST{$nextId}";
        } else {
            $validated['letter'] = strtoupper(trim($validated['letter']));
        }
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? MonitoredSite::count();

        MonitoredSite::create($validated);

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.sites.index')->with('success', 'Sede creada exitosamente.');
    }

    public function update(Request $request, MonitoredSite $site): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['nullable', 'string', 'max:255'],
            'phone_1' => ['nullable', 'string', 'max:100'],
            'phone_2' => ['nullable', 'string', 'max:100'],
            'phone_3' => ['nullable', 'string', 'max:100'],
            'phone_4' => ['nullable', 'string', 'max:100'],
            'phone_5' => ['nullable', 'string', 'max:100'],
            'phone_6' => ['nullable', 'string', 'max:100'],
            'phone_7' => ['nullable', 'string', 'max:100'],
            'phone_8' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'normal_state_msg' => ['nullable', 'string', 'max:255'],
            'error_state_msg' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (empty($validated['letter'])) {
            $validated['letter'] = "ST{$site->id}";
        } else {
            $validated['letter'] = strtoupper(trim($validated['letter']));
        }
        $validated['is_active'] = $request->boolean('is_active');

        $site->update($validated);

        // Procesar dispositivos si se enviaron en el formulario
        if ($request->has('devices')) {
            foreach ($request->input('devices', []) as $devId => $devData) {
                // Si está marcado para eliminación
                if (!empty($devData['_delete'])) {
                    if (is_numeric($devId)) {
                        $device = MonitoredSiteDevice::where('monitored_site_id', $site->id)->find($devId);
                        if ($device) {
                            $delIp = $device->ip;
                            $device->delete();
                            \App\Models\MonitoredNetworkDevice::where('ip', $delIp)->delete();
                        }
                    }
                    continue;
                }

                if (!empty($devData['name']) && !empty($devData['ip'])) {
                    $accType = strtoupper(trim($devData['access_type'] ?? 'SIN SOPORTE'));
                    $accPort = !empty($devData['access_port']) 
                        ? (int)$devData['access_port'] 
                        : ($accType === 'SSH' ? 22 : ($accType === 'TELNET' ? 23 : ($accType === 'WEB' ? 80 : ($accType === 'VNC' ? 5900 : null))));
                    $isActive = isset($devData['is_active']) && ($devData['is_active'] == '1' || $devData['is_active'] === true);

                    if (is_numeric($devId)) {
                        $device = MonitoredSiteDevice::where('monitored_site_id', $site->id)->find($devId);
                        if ($device) {
                            $oldIp = $device->ip;
                            $device->update([
                                'name' => $devData['name'],
                                'ip' => $devData['ip'],
                                'mac' => $devData['mac'] ?? null,
                                'vendor_data' => $devData['vendor_data'] ?? null,
                                'model' => $devData['model'] ?? null,
                                'serial' => $devData['serial'] ?? null,
                                'ports' => $devData['ports'] ?? null,
                                'access_type' => $accType,
                                'access_port' => $accPort,
                                'notes' => $devData['notes'] ?? null,
                                'is_active' => $isActive,
                            ]);

                            \App\Models\MonitoredNetworkDevice::updateOrCreate(
                                ['ip' => $oldIp],
                                [
                                    'monitored_site_id' => $site->id,
                                    'name' => $device->name,
                                    'ip' => $device->ip,
                                    'mac' => $device->mac,
                                    'vendor_data' => $device->vendor_data,
                                    'model' => $device->model,
                                    'serial' => $device->serial,
                                    'ports' => $device->ports,
                                    'access_type' => $device->access_type,
                                    'access_port' => $device->access_port,
                                    'notes' => $device->notes,
                                    'is_active' => $device->is_active,
                                ]
                            );
                        }
                    } else {
                        // Nuevo dispositivo agregado dinámicamente desde el modal de la sede
                        $maxNum = $site->devices()->max('device_number') ?? 0;
                        $newDev = $site->devices()->create([
                            'device_number' => $maxNum + 1,
                            'name' => $devData['name'],
                            'ip' => $devData['ip'],
                            'mac' => $devData['mac'] ?? null,
                            'vendor_data' => $devData['vendor_data'] ?? null,
                            'model' => $devData['model'] ?? null,
                            'serial' => $devData['serial'] ?? null,
                            'ports' => $devData['ports'] ?? null,
                            'access_type' => $accType,
                            'access_port' => $accPort,
                            'notes' => $devData['notes'] ?? null,
                            'is_active' => $isActive,
                        ]);

                        \App\Models\MonitoredNetworkDevice::updateOrCreate(
                            ['ip' => $newDev->ip],
                            [
                                'monitored_site_id' => $site->id,
                                'device_number' => $newDev->device_number,
                                'name' => $newDev->name,
                                'ip' => $newDev->ip,
                                'mac' => $newDev->mac,
                                'vendor_data' => $newDev->vendor_data,
                                'model' => $newDev->model,
                                'serial' => $newDev->serial,
                                'ports' => $newDev->ports,
                                'access_type' => $newDev->access_type,
                                'access_port' => $newDev->access_port,
                                'notes' => $newDev->notes,
                                'is_active' => $newDev->is_active,
                            ]
                        );
                    }
                }
            }
        }

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.sites.index')->with('success', 'Sede y equipos actualizados exitosamente.');
    }

    public function addDevice(Request $request, MonitoredSite $site): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'string', 'max:255'],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => ['nullable', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['access_type'] = strtoupper(trim($validated['access_type'] ?? 'SIN SOPORTE'));
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

        $maxNum = $site->devices()->max('device_number') ?? 0;
        $validated['device_number'] = $maxNum + 1;
        $validated['is_active'] = $request->boolean('is_active', true);

        $siteDevice = $site->devices()->create($validated);

        // Espejo en MonitoredNetworkDevice
        \App\Models\MonitoredNetworkDevice::updateOrCreate(
            ['ip' => $validated['ip']],
            [
                'monitored_site_id' => $site->id,
                'device_number' => $maxNum + 1,
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

        return back()->with('success', "Dispositivo agregado a [{$site->name}] exitosamente.");
    }

    public function deleteDevice(MonitoredSiteDevice $device): RedirectResponse
    {
        $siteName = $device->site->name ?? 'la sede';
        $deviceName = $device->name;
        $ip = $device->ip;

        // Eliminar también de MonitoredNetworkDevice
        \App\Models\MonitoredNetworkDevice::where('ip', $ip)->delete();

        $device->delete();

        app(SyncController::class)->exportToConfigFiles();

        return back()->with('success', "Dispositivo [{$deviceName}] eliminado de [{$siteName}] exitosamente.");
    }

    public function toggle(MonitoredSite $site): RedirectResponse
    {
        $site->is_active = !$site->is_active;
        $site->save();

        app(SyncController::class)->exportToConfigFiles();

        $status = $site->is_active ? 'activada' : 'desactivada';
        return back()->with('success', "Sede [{$site->name}] {$status} exitosamente.");
    }

    public function history(Request $request, MonitoredSite $site): \Illuminate\Http\JsonResponse
    {
        $range = $request->query('range', '24h');
        
        $query = $site->histories()->reorder('checked_at', 'asc');
        
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
        $avgDevicesOnline = $records->count() > 0 ? round($records->avg('devices_online'), 1) : 0.0;

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
                'devices_online' => $r->devices_online,
                'devices_total' => $r->devices_total,
                'status_message' => $r->status_message,
            ];
        }

        // Obtener caídas recientes para la tabla de incidentes (ordenadas de más reciente a más antigua)
        $incidents = $records->where('is_up', false)->sortByDesc('checked_at')->values()->map(function($r) {
            return [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'status_message' => $r->status_message ?: 'Enlace Caído / Timeout Ping ICMP',
                'devices_info' => "{$r->devices_online}/{$r->devices_total} equipos activos",
            ];
        });

        return response()->json([
            'success' => true,
            'site' => [
                'id' => $site->id,
                'letter' => $site->letter,
                'name' => $site->name,
                'ip' => $site->ip ?: '0.0.0.0',
                'address' => $site->address ?: 'Sin dirección registrada',
                'is_active' => $site->is_active,
                'devices_count' => $site->devices()->where('is_active', true)->count(),
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
                'avg_devices_online' => $avgDevicesOnline,
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

    public function destroy(MonitoredSite $site): RedirectResponse
    {
        $site->delete();

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.sites.index')->with('success', 'Sede eliminada exitosamente.');
    }
}
