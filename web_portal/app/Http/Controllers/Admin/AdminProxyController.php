<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredProxy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProxyController extends Controller
{
    public function index(): View
    {
        $proxies = MonitoredProxy::orderBy('letter')->get();
        return view('admin.proxies.index', compact('proxies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['required', 'string', 'max:5', 'unique:monitored_proxies'],
            'name' => ['required', 'string', 'max:255'],
            'ip_port' => ['required', 'string', 'max:255'],
            'auth_userpass' => ['nullable', 'string', 'max:255'],
            'test_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['letter'] = strtoupper($validated['letter']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['test_url'] = $validated['test_url'] ?? 'https://core.telegram.org/bots';

        MonitoredProxy::create($validated);

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.proxies.index')->with('success', 'Proxy registrado exitosamente.');
    }

    public function update(Request $request, MonitoredProxy $proxy): RedirectResponse
    {
        $validated = $request->validate([
            'letter' => ['required', 'string', 'max:5', Rule::unique('monitored_proxies')->ignore($proxy->id)],
            'name' => ['required', 'string', 'max:255'],
            'ip_port' => ['required', 'string', 'max:255'],
            'auth_userpass' => ['nullable', 'string', 'max:255'],
            'test_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['letter'] = strtoupper($validated['letter']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['test_url'] = $validated['test_url'] ?? 'https://core.telegram.org/bots';

        $proxy->update($validated);

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.proxies.index')->with('success', 'Proxy actualizado exitosamente.');
    }

    public function toggle(MonitoredProxy $proxy): RedirectResponse
    {
        $proxy->is_active = !$proxy->is_active;
        $proxy->save();

        app(SyncController::class)->exportToConfigFiles();

        $status = $proxy->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Proxy [{$proxy->name}] {$status} exitosamente.");
    }

    public function history(Request $request, MonitoredProxy $proxy): \Illuminate\Http\JsonResponse
    {
        $range = $request->query('range', '24h');
        
        $query = $proxy->histories()->reorder('checked_at', 'asc');
        
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

        // Obtener caídas recientes para la tabla de incidentes (ordenadas de más reciente a más antigua)
        $incidents = $records->where('is_up', false)->sortByDesc('checked_at')->values()->map(function($r) {
            return [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'status_message' => $r->status_message ?: 'Proxy Inaccesible / Conexión rechazada',
                'http_code' => $r->http_code ?: 'N/A',
            ];
        });

        return response()->json([
            'success' => true,
            'proxy' => [
                'id' => $proxy->id,
                'letter' => $proxy->letter,
                'name' => $proxy->name,
                'ip_port' => $proxy->ip_port,
                'has_auth' => !empty($proxy->auth_userpass),
                'is_active' => $proxy->is_active,
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

    public function destroy(MonitoredProxy $proxy): RedirectResponse
    {
        $proxy->delete();

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.proxies.index')->with('success', 'Proxy eliminado exitosamente.');
    }
}
