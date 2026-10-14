<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\NetworkTopologyInterface;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\NetworkTopologyLink;
use App\Models\SnmpDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controlador para la visualización y gestión interactiva de la Topología de Red.
 * Cumple con principios SOLID inyectando NetworkTopologyInterface.
 */
class AdminTopologyController extends Controller
{
    public function __construct(
        protected NetworkTopologyInterface $topologyService
    ) {}

    /**
     * Muestra el mapa interactivo de topología de red con Cytoscape.js.
     */
    public function index(Request $request): View
    {
        $selectedSiteId = $request->query('site_id') ? (int)$request->query('site_id') : null;

        $totalDevices = SnmpDevice::count() + MonitoredNetworkDevice::count() + InventoryAsset::whereNotNull('ip_address')->count();
        $totalLinks = NetworkTopologyLink::count();
        $activeLinks = NetworkTopologyLink::where('link_status', 'up')->count();
        $coreDevices = SnmpDevice::where('name', 'like', '%Core%')->orWhere('model', 'like', '%3750%')->count();

        $sites = MonitoredSite::where('is_active', true)->orderBy('name')->get();

        return view('admin.topology.index', compact(
            'totalDevices',
            'totalLinks',
            'activeLinks',
            'coreDevices',
            'sites',
            'selectedSiteId'
        ));
    }

    /**
     * Devuelve los elementos de grafo (nodes & edges) para Cytoscape.js en JSON.
     */
    public function data(Request $request): JsonResponse
    {
        $siteId = $request->query('site_id') ? (int)$request->query('site_id') : null;
        $graph = $this->topologyService->getTopologyGraph($siteId);

        return response()->json($graph);
    }

    /**
     * Reconstruye los enlaces de topología ejecutando el constructor backend.
     */
    public function rebuild(): RedirectResponse
    {
        $result = $this->topologyService->rebuildTopology();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'topology_rebuilt',
            'module' => 'topology',
            'description' => 'Topología de red re-evaluada y reconstruida manualmente.',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.topology.index')
            ->with('status', $result['message']);
    }
}
