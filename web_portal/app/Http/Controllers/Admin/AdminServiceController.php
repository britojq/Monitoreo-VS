<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminServiceController extends Controller
{
    public function index(): View
    {
        $services = MonitoredService::orderBy('sort_order')->get();
        return view('admin.services.index', compact('services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['WEB', 'DNS', 'SMTP', 'DHCP', 'CUPS', 'LDAP', 'PING', 'PROXY'])],
            'scope' => ['required', 'string', Rule::in(['corporativo', 'regional'])],
            'host_ip' => ['nullable', 'string', 'max:255'],
            'web_url' => ['nullable', 'string', 'max:500'],
            'port' => ['nullable', 'integer'],
            'credentials' => ['nullable', 'string', 'max:255'],
            'check_interface' => ['nullable', 'string', 'max:50'],
            'dns_test_domain' => ['nullable', 'string', 'max:255'],
            'normal_state_msg' => ['nullable', 'string', 'max:255'],
            'error_state_msg' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (empty($validated['letter'])) {
            $nextId = (MonitoredService::max('id') ?? 0) + 1;
            $validated['letter'] = "S{$nextId}";
        } else {
            $validated['letter'] = strtoupper(trim($validated['letter']));
        }
        $validated['scope'] = strtolower($validated['scope'] ?? 'corporativo');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? MonitoredService::count();

        MonitoredService::create($validated);

        // Disparar sincronización con archivo de configuración
        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.services.index')->with('success', 'Servicio agregado exitosamente.');
    }

    public function update(Request $request, MonitoredService $service): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['WEB', 'DNS', 'SMTP', 'DHCP', 'CUPS', 'LDAP', 'PING', 'PROXY'])],
            'scope' => ['required', 'string', Rule::in(['corporativo', 'regional'])],
            'host_ip' => ['nullable', 'string', 'max:255'],
            'web_url' => ['nullable', 'string', 'max:500'],
            'port' => ['nullable', 'integer'],
            'credentials' => ['nullable', 'string', 'max:255'],
            'check_interface' => ['nullable', 'string', 'max:50'],
            'dns_test_domain' => ['nullable', 'string', 'max:255'],
            'normal_state_msg' => ['nullable', 'string', 'max:255'],
            'error_state_msg' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (empty($validated['letter'])) {
            $validated['letter'] = "S{$service->id}";
        } else {
            $validated['letter'] = strtoupper(trim($validated['letter']));
        }
        $validated['scope'] = strtolower($validated['scope'] ?? 'corporativo');
        $validated['is_active'] = $request->boolean('is_active');

        $service->update($validated);

        // Disparar sincronización con archivo de configuración
        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.services.index')->with('success', 'Servicio actualizado exitosamente.');
    }

    public function toggle(MonitoredService $service): RedirectResponse
    {
        $service->is_active = !$service->is_active;
        $service->save();

        app(SyncController::class)->exportToConfigFiles();

        $status = $service->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Servicio [{$service->name}] {$status} exitosamente.");
    }

    public function history(Request $request, MonitoredService $service): \Illuminate\Http\JsonResponse
    {
        $range = $request->query('range', '24h');
        
        $query = $service->histories()->reorder('checked_at', 'asc');
        
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
                'http_code' => $r->http_code,
                'status_message' => $r->status_message,
            ];
        }

        // Obtener caídas recientes para el registro de incidentes (ordenadas de más reciente a más antigua)
        $incidents = $records->where('is_up', false)->sortByDesc('checked_at')->values()->map(function($r) {
            return [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'status_message' => $r->status_message ?: 'Timeout / Sin respuesta',
                'http_code' => $r->http_code ?: 'N/A',
            ];
        });

        $lastRecord = $records->last();

        return response()->json([
            'success' => true,
            'service' => [
                'id' => $service->id,
                'letter' => $service->letter,
                'name' => $service->name,
                'type' => $service->type,
                'target' => $service->host_ip ?: ($service->web_url ?: '127.0.0.1'),
                'is_active' => $service->is_active,
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

    public function destroy(MonitoredService $service): RedirectResponse
    {
        $service->delete();

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.services.index')->with('success', 'Servicio eliminado exitosamente.');
    }
}
