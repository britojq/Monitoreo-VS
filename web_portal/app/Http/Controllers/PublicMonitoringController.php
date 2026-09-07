<?php

namespace App\Http\Controllers;

use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoringSnapshot;
use App\Services\MonitoringDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicMonitoringController extends Controller
{
    public function index(MonitoringDataService $monitoringService): View
    {
        $data = $monitoringService->getMonitoringBoardData();
        return view('public.index', $data);
    }

    public function apiStatus(): JsonResponse
    {
        $latestSnapshot = MonitoringSnapshot::latest()->first();
        if ($latestSnapshot) {
            $payload = $latestSnapshot->payload_json;

            // Sanitización estricta para usuarios NO AUTENTICADOS (GUEST)
            if (!auth()->check()) {
                if (isset($payload['services']) && is_array($payload['services'])) {
                    foreach ($payload['services'] as &$s) {
                        unset($s['host_ip'], $s['web_url'], $s['port'], $s['latency_ms']);
                    }
                }
                if (isset($payload['sites']) && is_array($payload['sites'])) {
                    foreach ($payload['sites'] as &$st) {
                        unset($st['ip'], $st['latency_ms'], $st['phone_1'], $st['address'], $st['devices']);
                    }
                }
                if (isset($payload['network_devices']) && is_array($payload['network_devices'])) {
                    foreach ($payload['network_devices'] as &$nd) {
                        unset($nd['ip'], $nd['mac'], $nd['latency_ms'], $nd['vendor_data'], $nd['access_port']);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'snapshot' => $payload,
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
