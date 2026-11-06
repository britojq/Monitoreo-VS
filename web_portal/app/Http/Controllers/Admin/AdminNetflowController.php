<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetflowRecord;
use App\Models\NetflowTopTalker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNetflowController extends Controller
{
    /**
     * Muestra el panel de análisis de tráfico NetFlow v5 y Top Talkers.
     */
    public function index(Request $request): View
    {
        $exporterFilter = $request->query('exporter_ip');
        $protocolFilter = $request->query('protocol');
        $search = $request->query('search');

        // Métricas HUD globales
        $totalFlows = NetflowRecord::count();
        $totalBytes = NetflowRecord::sum('bytes') ?: 0;
        $totalPackets = NetflowRecord::sum('packets') ?: 0;
        $activeExporters = NetflowRecord::distinct('exporter_ip')->count('exporter_ip');

        // Top Talkers recientes (última ventana calculada)
        $latestWindow = NetflowTopTalker::max('window_start');

        $topSources = collect();
        $topDestinations = collect();
        $topProtocols = collect();

        if ($latestWindow) {
            $topSources = NetflowTopTalker::where('window_start', $latestWindow)
                ->where('rank_type', 'src_ip')
                ->orderByDesc('bytes')
                ->take(8)
                ->get();

            $topDestinations = NetflowTopTalker::where('window_start', $latestWindow)
                ->where('rank_type', 'dst_ip')
                ->orderByDesc('bytes')
                ->take(8)
                ->get();

            $topProtocols = NetflowTopTalker::where('window_start', $latestWindow)
                ->where('rank_type', 'protocol')
                ->orderByDesc('bytes')
                ->take(5)
                ->get();
        }

        // Flujos recientes paginados
        $query = NetflowRecord::orderByDesc('id');

        if (!empty($exporterFilter)) {
            $query->where('exporter_ip', $exporterFilter);
        }

        if ($protocolFilter !== null && $protocolFilter !== '') {
            $query->where('protocol', (int) $protocolFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('src_ip', 'like', "%{$search}%")
                  ->orWhere('dst_ip', 'like', "%{$search}%")
                  ->orWhere('src_port', 'like', "%{$search}%")
                  ->orWhere('dst_port', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate(20)->withQueryString();
        $exporters = NetflowRecord::distinct()->orderBy('exporter_ip')->pluck('exporter_ip');

        return view('admin.netflow.index', compact(
            'records',
            'topSources',
            'topDestinations',
            'topProtocols',
            'totalFlows',
            'totalBytes',
            'totalPackets',
            'activeExporters',
            'exporters',
            'latestWindow',
            'exporterFilter',
            'protocolFilter',
            'search'
        ));
    }

    /**
     * Endpoint API JSON para datos de Top Talkers y gráficos.
     */
    public function topTalkers(Request $request): JsonResponse
    {
        $type = $request->query('type', 'src_ip');
        $latestWindow = NetflowTopTalker::max('window_start');

        $talkers = NetflowTopTalker::where('window_start', $latestWindow)
            ->where('rank_type', $type)
            ->orderByDesc('bytes')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'window_start' => $latestWindow,
            'data' => $talkers,
        ]);
    }
}
