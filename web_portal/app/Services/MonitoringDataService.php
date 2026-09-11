<?php

namespace App\Services;

use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoringSnapshot;
use App\Models\NetworkDeviceCheckHistory;
use App\Models\ServiceCheckHistory;
use App\Models\SiteCheckHistory;

class MonitoringDataService
{
    /**
     * Obtiene la estructura integral de datos para el tablero de monitoreo en tiempo real.
     * Compartido entre la vista pública y el panel administrativo.
     */
    public function getMonitoringBoardData(): array
    {
        // 1. Filtrar estrictamente solo servicios reales configurados y activos
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

        // 2. Filtrar estrictamente sedes reales configuradas y activas
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

        // 3. Proxies reales activos
        $proxies = MonitoredProxy::where('is_active', true)
            ->where('name', 'not like', '%NO CONFIGURADO%')
            ->whereNotNull('ip_port')
            ->where('ip_port', '!=', '')
            ->get();

        // 4. Dispositivos locales en red Valle Seco
        $networkDevices = MonitoredNetworkDevice::where('is_active', true)
            ->where('name', 'not like', '%NO CONFIGURADO%')
            ->whereNotNull('ip')
            ->where('ip', '!=', '0.0.0.0')
            ->orderBy('sort_order')
            ->get();

        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotData = $latestSnapshot ? $latestSnapshot->payload_json : null;

        // 5. Cargar historial de 24h para servicios
        $serviceHistories = ServiceCheckHistory::whereIn('monitored_service_id', $services->pluck('id'))
            ->where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at', 'asc')
            ->get()
            ->groupBy('monitored_service_id');

        $serviceHistoryMap = [];
        $snapshotServices = ($snapshotData && isset($snapshotData['services'])) ? collect($snapshotData['services'])->keyBy('id') : collect();

        foreach ($services as $s) {
            $records = $serviceHistories->get($s->id) ?? collect();
            $totalChecks = $records->count();

            // Si no hay registros en las últimas 24h, recuperar los últimos 50 chequeos registrados
            if ($totalChecks === 0) {
                $records = ServiceCheckHistory::where('monitored_service_id', $s->id)
                    ->latest('checked_at')
                    ->take(50)
                    ->get()
                    ->reverse();
                $totalChecks = $records->count();
            }

            $evaluatedSvc = $snapshotServices->get($s->id);
            $isLiveUp = $evaluatedSvc ? (($evaluatedSvc['status'] ?? '') === 'ACTIVO') : $s->is_active;
            $liveLatency = $evaluatedSvc ? (float)($evaluatedSvc['latency_ms'] ?? 1.2) : 1.2;

            if ($totalChecks === 0) {
                $uptimePct = $isLiveUp ? 100.0 : 0.0;
                $avgLatency = $liveLatency;
                $minLatency = $liveLatency;
                $maxLatency = $liveLatency;
                $labels = [now()->subMinutes(5)->format('H:i'), now()->format('H:i')];
                $latencies = [$liveLatency, $liveLatency];
                $statuses = [$isLiveUp ? 1 : 0, $isLiveUp ? 1 : 0];
                $hasData = true;
                $downChecks = $isLiveUp ? 0 : 1;
            } else {
                $upRecords = $records->where('is_up', true);
                $upChecks = $upRecords->count();
                $downChecks = $totalChecks - $upChecks;
                $uptimePct = round(($upChecks / $totalChecks) * 100, 1);
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
                $hasData = true;
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
                'has_data' => $hasData,
            ];
        }

        // 6. Cargar historial de 24h para sedes
        $siteHistories = SiteCheckHistory::whereIn('monitored_site_id', $sites->pluck('id'))
            ->where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at', 'asc')
            ->get()
            ->groupBy('monitored_site_id');

        $siteHistoryMap = [];
        $snapshotSites = ($snapshotData && isset($snapshotData['sites'])) ? collect($snapshotData['sites'])->keyBy('id') : collect();

        foreach ($sites as $st) {
            $records = $siteHistories->get($st->id) ?? collect();
            $totalChecks = $records->count();

            // Si no hay registros en las últimas 24h, recuperar los últimos 50 chequeos registrados
            if ($totalChecks === 0) {
                $records = SiteCheckHistory::where('monitored_site_id', $st->id)
                    ->latest('checked_at')
                    ->take(50)
                    ->get()
                    ->reverse();
                $totalChecks = $records->count();
            }

            $evaluatedSite = $snapshotSites->get($st->id);
            $isLiveUp = $evaluatedSite ? (($evaluatedSite['status'] ?? '') === 'ACTIVO') : $st->is_active;
            $liveLatency = $evaluatedSite ? (float)($evaluatedSite['latency_ms'] ?? 2.5) : 2.5;

            if ($totalChecks === 0) {
                $uptimePct = $isLiveUp ? 100.0 : 0.0;
                $avgLatency = $liveLatency;
                $minLatency = $liveLatency;
                $maxLatency = $liveLatency;
                $labels = [now()->subMinutes(5)->format('H:i'), now()->format('H:i')];
                $latencies = [$liveLatency, $liveLatency];
                $statuses = [$isLiveUp ? 1 : 0, $isLiveUp ? 1 : 0];
                $hasData = true;
                $downChecks = $isLiveUp ? 0 : 1;
            } else {
                $upRecords = $records->where('is_up', true);
                $upChecks = $upRecords->count();
                $downChecks = $totalChecks - $upChecks;
                $uptimePct = round(($upChecks / $totalChecks) * 100, 1);
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
                $hasData = true;
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
                'has_data' => $hasData,
            ];
        }

        // 7. Cargar historial de 24h para dispositivos de red Valle Seco
        $deviceHistories = NetworkDeviceCheckHistory::whereIn('monitored_network_device_id', $networkDevices->pluck('id'))
            ->where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at', 'asc')
            ->get()
            ->groupBy('monitored_network_device_id');

        $deviceHistoryMap = [];
        $snapshotNetDevices = ($snapshotData && isset($snapshotData['network_devices'])) ? collect($snapshotData['network_devices'])->keyBy('id') : collect();

        foreach ($networkDevices as $nd) {
            $records = $deviceHistories->get($nd->id) ?? collect();
            $totalChecks = $records->count();
            $upRecords = $records->where('is_up', true);
            $upChecks = $upRecords->count();
            $downChecks = $totalChecks - $upChecks;

            $evaluatedDev = $snapshotNetDevices->get($nd->id);
            $isLiveUp = $evaluatedDev ? (bool)($evaluatedDev['is_up'] ?? false) : true;
            $liveLatency = $evaluatedDev ? (float)($evaluatedDev['latency_ms'] ?? 0.8) : 0.8;

            if ($totalChecks === 0) {
                $uptimePct = $isLiveUp ? 100.0 : 0.0;
                $avgLatency = $liveLatency;
                $minLatency = $liveLatency;
                $maxLatency = $liveLatency;
                $labels = [now()->subMinutes(5)->format('H:i'), now()->format('H:i')];
                $latencies = [$liveLatency, $liveLatency];
                $statuses = [$isLiveUp ? 1 : 0, $isLiveUp ? 1 : 0];
                $hasData = true;
            } else {
                $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 1) : ($isLiveUp ? 100.0 : 0.0);
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
                $hasData = $totalChecks > 0;
            }

            $deviceHistoryMap[$nd->id] = [
                'labels' => $labels,
                'latencies' => $latencies,
                'statuses' => $statuses,
                'uptime_pct' => $uptimePct,
                'down_checks' => $downChecks,
                'avg_latency' => $avgLatency,
                'min_latency' => $minLatency,
                'max_latency' => $maxLatency,
                'has_data' => $hasData,
            ];
        }

        // 8. Evaluaciones de estados agregados y conteos
        $snapshotServices = ($snapshotData && isset($snapshotData['services'])) ? collect($snapshotData['services'])->keyBy('letter') : collect();
        $snapshotSites = ($snapshotData && isset($snapshotData['sites'])) ? collect($snapshotData['sites'])->keyBy('letter') : collect();

        $activeServices = $services->filter(function($s) use ($snapshotServices) {
            $snap = $snapshotServices->get($s->letter);
            return $snap ? ($snap['is_up'] ?? false) : false;
        });
        $downServices = $services->filter(function($s) use ($snapshotServices) {
            $snap = $snapshotServices->get($s->letter);
            return $snap ? !($snap['is_up'] ?? false) : true;
        });

        $activeSites = $sites->filter(function($st) use ($snapshotSites) {
            $snap = $snapshotSites->get($st->letter);
            return $snap ? ($snap['is_up'] ?? false) : false;
        });
        $downSites = $sites->filter(function($st) use ($snapshotSites) {
            $snap = $snapshotSites->get($st->letter);
            return $snap ? !($snap['is_up'] ?? false) : true;
        });

        $activeNetDevices = ($networkDevices ?? collect())->map(function($d) use ($snapshotNetDevices) {
            $snap = $snapshotNetDevices->get($d->ip);
            $d->is_up_evaluated = $snap ? ($snap['is_up'] ?? false) : false;
            $d->latency_evaluated = $snap ? ($snap['latency_ms'] ?? 0) : 0;
            return $d;
        });
        $netDevicesOnlineCount = $activeNetDevices->where('is_up_evaluated', true)->count();

        return compact(
            'services',
            'sites',
            'proxies',
            'networkDevices',
            'latestSnapshot',
            'snapshotData',
            'serviceHistoryMap',
            'siteHistoryMap',
            'deviceHistoryMap',
            'snapshotServices',
            'snapshotSites',
            'snapshotNetDevices',
            'activeServices',
            'downServices',
            'activeSites',
            'downSites',
            'activeNetDevices',
            'netDevicesOnlineCount'
        );
    }
}
