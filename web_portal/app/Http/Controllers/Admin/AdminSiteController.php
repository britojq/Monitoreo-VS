<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSiteController extends Controller
{
    public function index(): View
    {
        $sites = MonitoredSite::with('devices')->orderBy('sort_order')->get();
        return view('admin.sites.index', compact('sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['required', 'string', 'max:5', 'unique:monitored_sites'],
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

        $validated['letter'] = strtoupper($validated['letter']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? MonitoredSite::count();

        $site = MonitoredSite::create($validated);

        // Crear 8 slots de dispositivos vacíos
        for ($i = 1; $i <= 8; $i++) {
            $site->devices()->create([
                'device_number' => $i,
                'name' => "Equipo {$i}",
                'ip' => '0.0.0.0',
                'is_active' => false,
            ]);
        }

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.sites.index')->with('success', 'Sede creada exitosamente.');
    }

    public function update(Request $request, MonitoredSite $site): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['required', 'string', 'max:5', Rule::unique('monitored_sites')->ignore($site->id)],
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

        $validated['letter'] = strtoupper($validated['letter']);
        $validated['is_active'] = $request->boolean('is_active');

        $site->update($validated);

        // Procesar dispositivos si se enviaron en el formulario
        if ($request->has('devices')) {
            foreach ($request->input('devices', []) as $devId => $devData) {
                $device = MonitoredSiteDevice::where('monitored_site_id', $site->id)->find($devId);
                if ($device) {
                    $device->update([
                        'name' => $devData['name'] ?? $device->name,
                        'ip' => $devData['ip'] ?? $device->ip,
                        'is_active' => isset($devData['is_active']) && $devData['is_active'] == '1',
                    ]);
                }
            }
        }

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.sites.index')->with('success', 'Sede y equipos actualizados exitosamente.');
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
