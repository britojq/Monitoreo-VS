<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoringSnapshot;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClusterApiController extends Controller
{
    protected ClusterConfigService $clusterService;

    public function __construct(ClusterConfigService $clusterService)
    {
        $this->clusterService = $clusterService;
    }

    /**
     * Comprueba la conectividad y validez de credenciales entre Master y Slave.
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'node_role' => $this->clusterService->getNodeRole(),
            'timestamp' => date('Y-m-d H:i:s'),
            'hostname' => gethostname(),
            'message' => 'Cluster API activa y en línea.',
        ]);
    }

    /**
     * Entrega el snapshot completo y la configuración de monitoreo al nodo Slave.
     */
    public function telemetry(Request $request): JsonResponse
    {
        $snapshotPath = storage_path('app/public/monitoring_snapshot.json');
        $snapshotPayload = null;

        if (file_exists($snapshotPath)) {
            $content = @file_get_contents($snapshotPath);
            $snapshotPayload = json_decode($content, true);
        }

        if (!$snapshotPayload) {
            $latestDb = MonitoringSnapshot::latest()->first();
            $snapshotPayload = $latestDb ? $latestDb->payload_json : [];
        }

        return response()->json([
            'success' => true,
            'node_role' => $this->clusterService->getNodeRole(),
            'generated_at' => $snapshotPayload['timestamp'] ?? date('Y-m-d H:i:s'),
            'snapshot' => $snapshotPayload,
            'config' => [
                'services' => MonitoredService::orderBy('sort_order')->get(),
                'sites' => MonitoredSite::with('devices')->orderBy('sort_order')->get(),
                'proxies' => MonitoredProxy::orderBy('letter')->get(),
                'network_devices' => MonitoredNetworkDevice::orderBy('sort_order')->get(),
            ],
        ]);
    }
}
