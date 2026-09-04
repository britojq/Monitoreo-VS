<?php

namespace App\Http\Controllers;

use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoringSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicMonitoringController extends Controller
{
    public function index(): View
    {
        // Filtrar estrictamente solo servicios reales configurados y activos
        $services = MonitoredService::where('is_active', true)
            ->where('name', 'not like', '%NO CONFIGURADO%')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('host_ip')
                       ->where('host_ip', '!=', '0.0.0.0')
                       ->where('host_ip', '!=', '127.0.0.1');
                })->orWhere(function ($q3) {
                    $q3->whereNotNull('web_url')
                       ->where('web_url', '!=', '')
                       ->where('web_url', 'not like', '%127.0.0.1%');
                });
            })
            ->orderBy('sort_order')
            ->get();

        // Filtrar estrictamente sedes reales configuradas y activas
        $sites = MonitoredSite::with(['devices' => function ($q) {
                $q->where('is_active', true)
                  ->where('name', 'not like', '%NO CONFIGURADO%')
                  ->where('ip', '!=', '0.0.0.0');
            }])
            ->where('is_active', true)
            ->where('name', 'not like', '%NO CONFIGURADO%')
            ->whereNotNull('ip')
            ->where('ip', '!=', '0.0.0.0')
            ->orderBy('sort_order')
            ->get();

        // Proxies reales activos
        $proxies = MonitoredProxy::where('is_active', true)
            ->where('name', 'not like', '%NO CONFIGURADO%')
            ->whereNotNull('ip_port')
            ->where('ip_port', '!=', '')
            ->get();

        // Dispositivos locales en red Valle Seco
        $networkDevices = MonitoredNetworkDevice::where('is_active', true)
            ->where('name', 'not like', '%NO CONFIGURADO%')
            ->whereNotNull('ip')
            ->where('ip', '!=', '0.0.0.0')
            ->orderBy('sort_order')
            ->get();

        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotData = $latestSnapshot ? $latestSnapshot->payload_json : null;

        // Cargar historial de 24h para servicios (exactamente igual que AdminServiceController)
        $serviceHistories = \App\Models\ServiceCheckHistory::whereIn('monitored_service_id', $services->pluck('id'))
            ->where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at', 'asc')
            ->get()
            ->groupBy('monitored_service_id');

        $serviceHistoryMap = [];
        foreach ($services as $s) {
            $records = $serviceHistories->get($s->id) ?? collect();
            $totalChecks = $records->count();
            $upRecords = $records->where('is_up', true);
            $upChecks = $upRecords->count();
            $downChecks = $totalChecks - $upChecks;
            $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 1) : 0.0;
            $avgLatency = $upRecords->count() > 0 ? round($upRecords->avg('latency_ms'), 1) : 0.0;
            $maxLatency = $upRecords->count() > 0 ? round($upRecords->max('latency_ms'), 1) : 0.0;
            $minLatency = $upRecords->count() > 0 ? round($upRecords->min('latency_ms'), 1) : 0.0;

            $labels = [];
            $latencies = [];
            $statuses = [];
            foreach ($records as $r) {
                $labels[] = $r->checked_at ? $r->checked_at->format('H:i') : '';
                $latencies[] = $r->is_up ? round((float)$r->latency_ms, 1) : 0.0;
                $statuses[] = $r->is_up ? 1 : 0;
            }

            $serviceHistoryMap[$s->id] = [
                'labels' => $labels,
                'latencies' => $latencies,
                'statuses' => $statuses,
                'uptime_pct' => $uptimePct,
                'down_checks' => $downChecks,
                'avg_latency' => $avgLatency,
                'min_latency' => $minLatency,
                'max_latency' => $maxLatency,
                'has_data' => $totalChecks > 0 && $upChecks > 0,
            ];
        }

        // Cargar historial de 24h para sedes (exactamente igual que AdminServiceController)
        $siteHistories = \App\Models\SiteCheckHistory::whereIn('monitored_site_id', $sites->pluck('id'))
            ->where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at', 'asc')
            ->get()
            ->groupBy('monitored_site_id');

        $siteHistoryMap = [];
        foreach ($sites as $st) {
            $records = $siteHistories->get($st->id) ?? collect();
            $totalChecks = $records->count();
            $upRecords = $records->where('is_up', true);
            $upChecks = $upRecords->count();
            $downChecks = $totalChecks - $upChecks;
            $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 1) : 0.0;
            $avgLatency = $upRecords->count() > 0 ? round($upRecords->avg('latency_ms'), 1) : 0.0;
            $maxLatency = $upRecords->count() > 0 ? round($upRecords->max('latency_ms'), 1) : 0.0;
            $minLatency = $upRecords->count() > 0 ? round($upRecords->min('latency_ms'), 1) : 0.0;

            $labels = [];
            $latencies = [];
            $statuses = [];
            foreach ($records as $r) {
                $labels[] = $r->checked_at ? $r->checked_at->format('H:i') : '';
                $latencies[] = $r->is_up ? round((float)$r->latency_ms, 1) : 0.0;
                $statuses[] = $r->is_up ? 1 : 0;
            }

            $siteHistoryMap[$st->id] = [
                'labels' => $labels,
                'latencies' => $latencies,
                'statuses' => $statuses,
                'uptime_pct' => $uptimePct,
                'down_checks' => $downChecks,
                'avg_latency' => $avgLatency,
                'min_latency' => $minLatency,
                'max_latency' => $maxLatency,
                'has_data' => $totalChecks > 0 && $upChecks > 0,
            ];
        }

        return view('public.index', compact('services', 'sites', 'proxies', 'networkDevices', 'latestSnapshot', 'snapshotData', 'serviceHistoryMap', 'siteHistoryMap'));
    }

    public function apiStatus(): JsonResponse
    {
        $latestSnapshot = MonitoringSnapshot::latest()->first();
        if ($latestSnapshot) {
            return response()->json([
                'success' => true,
                'snapshot' => $latestSnapshot->payload_json,
                'updated_at' => $latestSnapshot->created_at->format('Y-m-d H:i:s'),
                'updated_at_human' => $latestSnapshot->created_at->diffForHumans(),
                'global_status' => $latestSnapshot->global_status,
                'metrics' => [
                    'services_online' => $latestSnapshot->services_online,
                    'services_total' => $latestSnapshot->services_total,
                    'sites_online' => $latestSnapshot->sites_online,
                    'sites_total' => $latestSnapshot->sites_total,
                    'proxies_online' => $latestSnapshot->proxies_online,
                    'proxies_total' => $latestSnapshot->proxies_total,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No hay snapshots de monitoreo disponibles aún.',
        ], 404);
    }
}
