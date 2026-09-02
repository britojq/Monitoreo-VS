<?php

namespace App\Http\Controllers;

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

        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotData = $latestSnapshot ? $latestSnapshot->payload_json : null;

        return view('public.index', compact('services', 'sites', 'proxies', 'latestSnapshot', 'snapshotData'));
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
