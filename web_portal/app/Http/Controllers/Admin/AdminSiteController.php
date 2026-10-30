<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Site\AttachDeviceToSiteAction;
use App\Actions\Site\UpdateSiteAction;
use App\Http\Controllers\Controller;
use App\Models\InventoryAsset;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoringSnapshot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $unlinkedAssets = InventoryAsset::orderBy('name')->get();

        return view('admin.sites.index', compact('sites', 'networkDevices', 'snapshotDevices', 'unlinkedAssets'));
    }

    /**
     * Reordena la posición de las sedes (Drag & Drop) y persiste en MariaDB.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:monitored_sites,id'],
        ]);

        foreach ($validated['order'] as $index => $siteId) {
            MonitoredSite::where('id', $siteId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden de sedes actualizado exitosamente en el sistema.',
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
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

        $site = MonitoredSite::create($validated);

        app(SyncController::class)->exportToConfigFiles();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Sede creada exitosamente.',
                'site' => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'ip' => $site->ip,
                    'letter' => $site->letter,
                ],
            ]);
        }

        return redirect()->route('admin.sites.index')->with('success', 'Sede creada exitosamente.');
    }

    public function update(Request $request, MonitoredSite $site, UpdateSiteAction $action): RedirectResponse
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

        $action->execute($site, $request->all(), $request->boolean('is_active'));

        return redirect()->route('admin.sites.index')->with('success', 'Sede y equipos actualizados exitosamente.');
    }

    public function addDevice(Request $request, MonitoredSite $site, AttachDeviceToSiteAction $action): RedirectResponse
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
            'telegram_alert_enabled' => ['nullable', 'boolean'],
            'telegram_alert_target' => ['nullable', 'string', Rule::in(['owner', 'group'])],
            'sync_inventory' => ['nullable', 'boolean'],
            'inventory_action' => ['nullable', 'string', Rule::in(['create', 'link', 'none'])],
            'asset_type' => ['nullable', 'string', Rule::in(['desktop', 'switch', 'router', 'server', 'printer', 'network'])],
            'asset_status' => ['nullable', 'string', Rule::in(['assigned', 'in_stock', 'maintenance', 'decommissioned'])],
            'existing_asset_id' => ['nullable', 'integer'],
        ]);

        $action->execute($site, $request->all());

        return back()->with('success', "Dispositivo agregado a [{$site->name}] y sincronizado con inventario exitosamente.");
    }

    public function deleteDevice(MonitoredSiteDevice $device): RedirectResponse
    {
        $siteName = $device->site->name ?? 'la sede';
        $deviceName = $device->name;
        $ip = $device->ip;

        // Desvincular otros activos de inventario asociados sin eliminar el activo físico
        InventoryAsset::where('id', '!=', $device->id)
            ->where(function ($q) use ($device, $ip) {
                $q->where('monitored_network_device_id', $device->id)
                  ->orWhere('ip_address', $ip);
            })
            ->update(['monitored_network_device_id' => null]);

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
        $range = (string) $request->query('range', '24h');
        $since = match ($range) {
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $stats = DB::table('site_check_histories')
            ->where('monitored_site_id', $site->id)
            ->where('checked_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_checks,
                SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_checks,
                AVG(CASE WHEN is_up = 1 THEN latency_ms ELSE NULL END) as avg_latency,
                MAX(CASE WHEN is_up = 1 THEN latency_ms ELSE 0 END) as max_latency,
                MIN(CASE WHEN is_up = 1 THEN latency_ms ELSE NULL END) as min_latency,
                AVG(devices_online) as avg_devices_online
            ')->first();

        $totalChecks = (int) ($stats->total_checks ?? 0);
        $upChecks = (int) ($stats->up_checks ?? 0);
        $downChecks = $totalChecks - $upChecks;
        $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : 0.0;
        $avgLatency = round((float) ($stats->avg_latency ?? 0), 2);
        $maxLatency = round((float) ($stats->max_latency ?? 0), 2);
        $minLatency = round((float) ($stats->min_latency ?? 0), 2);
        $avgDevicesOnline = round((float) ($stats->avg_devices_online ?? 0), 1);

        $rawRecords = DB::table('site_check_histories')
            ->where('monitored_site_id', $site->id)
            ->where('checked_at', '>=', $since)
            ->select(['checked_at', 'is_up', 'latency_ms', 'devices_online', 'devices_total', 'status_message'])
            ->orderBy('checked_at', 'asc')
            ->get();

        $sampled = [];
        $lastState = null;
        $step = max(1, (int) ceil($rawRecords->count() / 150));

        foreach ($rawRecords as $idx => $r) {
            $currentState = (bool) $r->is_up;
            $isStateChange = ($lastState !== null && $lastState !== $currentState);
            $lastState = $currentState;

            if ($isStateChange || $idx % $step === 0 || $idx === $rawRecords->count() - 1) {
                $sampled[] = $r;
            }
        }

        $labels = [];
        $latencies = [];
        $statuses = [];
        $points = [];

        foreach ($sampled as $r) {
            $dt = Carbon::parse($r->checked_at);
            $formattedTime = ($range === '7d' || $range === '30d')
                ? $dt->format('d/m H:i')
                : $dt->format('H:i');

            $labels[] = $formattedTime;
            $latencies[] = $r->is_up ? (float) $r->latency_ms : 0.0;
            $statuses[] = $r->is_up ? 1 : 0;
            $points[] = [
                'time' => $dt->format('Y-m-d H:i:s'),
                'is_up' => (bool) $r->is_up,
                'latency_ms' => (float) $r->latency_ms,
                'devices_online' => $r->devices_online,
                'devices_total' => $r->devices_total,
                'status_message' => $r->status_message,
            ];
        }

        $incidents = $rawRecords->where('is_up', 0)->sortByDesc('checked_at')->take(30)->map(function ($r) {
            $dt = Carbon::parse($r->checked_at);
            return [
                'time' => $dt->format('Y-m-d H:i:s'),
                'time_human' => $dt->diffForHumans(),
                'status_message' => $r->status_message ?: 'Enlace Caído / Timeout Ping ICMP',
                'devices_info' => "{$r->devices_online}/{$r->devices_total} equipos activos",
            ];
        })->values();

        $lastRecord = $rawRecords->last();
        $lastDt = $lastRecord ? Carbon::parse($lastRecord->checked_at) : null;

        return response()->json([
            'success' => true,
            'site' => [
                'id' => $site->id,
                'letter' => $site->letter,
                'name' => $site->name,
                'ip' => $site->ip ?: '0.0.0.0',
                'address' => $site->address ?: 'Sin dirección registrada',
                'is_active' => (bool) $site->is_active,
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
                'last_check' => $lastDt ? $lastDt->format('Y-m-d H:i:s') : null,
                'last_check_human' => $lastDt ? $lastDt->diffForHumans() : null,
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
