<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Deploy\BulkPromoteAssetsAction;
use App\Actions\Deploy\FetchDeployDashboardDataAction;
use App\Actions\Deploy\PromoteGlpiAssetAction;
use App\Contracts\GlpiAgentTriggerInterface;
use App\Contracts\GlpiEndpointConfigInterface;
use App\Contracts\HardwareDriftDetectorInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkPromoteAssetsRequest;
use App\Http\Requests\Admin\DeployAgentRequest;
use App\Http\Requests\Admin\PromoteAssetRequest;
use App\Http\Requests\Admin\ScanSubnetRequest;
use App\Http\Requests\Admin\UpdateGlpiEndpointsRequest;
use App\Services\Deployment\AgentDeployService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controlador delgado para la interfaz de despliegue remoto de agentes GLPI.
 * Arquitectura SOLID: métodos <15 líneas, validación desacoplada y lógica en Actions.
 */
class AdminGlpiDeployController extends Controller
{
    public function __construct(
        protected AgentDeployService $deployService,
        protected HardwareDriftDetectorInterface $driftDetector,
        protected GlpiAgentTriggerInterface $agentTrigger,
        protected GlpiEndpointConfigInterface $endpointConfig
    ) {}

    /**
     * Muestra la interfaz visual interactiva de Despliegue Remoto de Agente GLPI.
     */
    public function index(Request $request, FetchDeployDashboardDataAction $action): View
    {
        $data = $action->execute($request);

        return view('admin.inventory.deploy.index', $data);
    }

    /**
     * Actualiza las configuraciones de endpoints y tokens GLPI en MariaDB.
     */
    public function updateEndpoints(UpdateGlpiEndpointsRequest $request): JsonResponse
    {
        $this->endpointConfig->updateEndpoints($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Configuración de endpoints GLPI actualizada correctamente en la base de datos central.',
            'endpoints' => $this->endpointConfig->getEndpointsConfig(),
        ]);
    }

    /**
     * Ejecuta el despliegue o conmutación remota vía AJAX hacia un equipo seleccionado.
     */
    public function deploy(DeployAgentRequest $request): JsonResponse
    {
        $v = $request->validated();

        $result = $this->deployService->deploy(
            $v['host'],
            $v['os'],
            $v['user'],
            $v['target'],
            $v['action'],
            $v['password'] ?? null,
            $v['domain'] ?? null,
            $v['custom_ip'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Consulta rápida de verificación en base de datos.
     */
    public function quickStatus(Request $request): JsonResponse
    {
        $result = $this->deployService->quickStatus((string) $request->input('ip'));
        $statusCode = $result['status_code'] ?? 200;
        unset($result['status_code']);

        return response()->json($result, $statusCode);
    }

    /**
     * Incorpora un dispositivo descubierto al inventario oficial ITAM y red.
     */
    public function promoteToAsset(PromoteAssetRequest $request, PromoteGlpiAssetAction $action): JsonResponse
    {
        $result = $action->execute($request->validated(), $request->ip(), $request->userAgent());

        return response()->json($result);
    }

    /**
     * Incorpora múltiples dispositivos descubiertos por lote al inventario oficial ITAM.
     */
    public function bulkPromote(BulkPromoteAssetsRequest $request, BulkPromoteAssetsAction $action): JsonResponse
    {
        $result = $action->execute($request->validated(), $request->ip(), $request->userAgent());

        return response()->json($result);
    }

    /**
     * Escanea un segmento de red CIDR específico bajo demanda y devuelve los resultados vía AJAX.
     */
    public function scanSubnet(ScanSubnetRequest $request): JsonResponse
    {
        $result = $this->deployService->scanSubnet((string) $request->input('subnet'));
        $statusCode = $result['status_code'] ?? 200;
        unset($result['status_code']);

        return response()->json($result, $statusCode);
    }

    /**
     * Determina si una dirección IPv4 pertenece a una subred CIDR.
     */
    public function ipInSubnet(string $ip, string $range): bool
    {
        return $this->deployService->ipInSubnet($ip, $range);
    }

    /**
     * Envía una señal HTTP de sondeo inmediato (puerto 62354) al agente GLPI del equipo remoto.
     */
    public function triggerAgent(Request $request): JsonResponse
    {
        $ip = trim((string) $request->input('ip'));
        if (empty($ip)) {
            return response()->json([
                'success' => false,
                'message' => 'Dirección IP no especificada para el sondeo del agente.',
            ], 422);
        }

        $result = $this->agentTrigger->triggerImmediateInventory($ip);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Devuelve el resumen y la lista de discrepancias forenses de hardware (Hardware Drift) vía AJAX.
     */
    public function driftSummary(Request $request): JsonResponse
    {
        $siteId = $request->query('site_id') ? (int) $request->query('site_id') : null;

        return response()->json([
            'summary' => $this->driftDetector->getDriftSummary(),
            'assets' => $this->driftDetector->getAssetsWithDrift($siteId),
        ]);
    }

    /**
     * Registra la justificación y regularización formal de una discrepancia de hardware.
     */
    public function acknowledgeDrift(Request $request): JsonResponse
    {
        $request->validate([
            'asset_id' => ['required', 'integer', 'exists:inventory_assets,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $assetId = (int) $request->input('asset_id');
        $reason = (string) $request->input('reason');
        $userId = Auth::id() ?? 1;

        $ok = $this->driftDetector->acknowledgeDrift($assetId, $reason, $userId);
        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo regularizar la discrepancia del activo.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Discrepancia de hardware regularizada y asentada en la bitácora de auditoría.',
        ]);
    }
}
