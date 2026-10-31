<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Cluster\ProvideClusterDiagnosticsAction;
use App\Actions\Cluster\ProvideClusterTelemetryAction;
use App\Actions\Cluster\VerifyClusterAction;
use App\Contracts\ClusterAuditLoggerInterface;
use App\Contracts\ClusterVerifierInterface;
use App\Http\Controllers\Controller;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClusterApiController extends Controller
{
    public function __construct(
        protected ClusterConfigService $clusterService,
        protected ?ClusterVerifierInterface $verifier = null,
        protected ?ClusterAuditLoggerInterface $auditLogger = null
    ) {}

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

    public function telemetry(Request $request, ProvideClusterTelemetryAction $action): Response
    {
        return $action->execute($request);
    }

    public function diagnostics(Request $request, ProvideClusterDiagnosticsAction $action): JsonResponse
    {
        return $action->diagnostics($request);
    }

    public function verify(Request $request, VerifyClusterAction $action): JsonResponse
    {
        return $action->execute($request);
    }

    public function auditTrail(Request $request, ProvideClusterDiagnosticsAction $action): JsonResponse
    {
        return $action->auditTrail($request);
    }
}
