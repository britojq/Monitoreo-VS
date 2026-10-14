<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NexusAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API de Administración Remota Zero-SSH (Nexus Engine)
 * Ecosistema NOC + ITAM - Plataforma Integral Valle Seco
 */
class NexusAdminController extends Controller
{
    protected NexusAdminService $nexusService;

    public function __construct(NexusAdminService $nexusService)
    {
        $this->nexusService = $nexusService;
    }

    /**
     * Endpoint central despachador de operaciones administrativas.
     */
    public function dispatchAction(Request $request, ?string $action = null): JsonResponse
    {
        $resolvedAction = $action ?: (string) $request->input('action', 'status');
        $params = $request->except(['action', 'nexus_token']);

        $clientIp = $request->ip() ?: 'unknown';
        $result = $this->nexusService->dispatch($resolvedAction, $params, $clientIp);

        $httpCode = ($result['success'] ?? false) ? 200 : 400;

        return response()->json($result, $httpCode);
    }
}
