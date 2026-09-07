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
