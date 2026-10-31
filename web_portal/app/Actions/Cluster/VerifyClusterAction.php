<?php

declare(strict_types=1);

namespace App\Actions\Cluster;

use App\Contracts\ClusterAuditLoggerInterface;
use App\Contracts\ClusterVerifierInterface;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyClusterAction
{
    public function __construct(
        protected ClusterConfigService $clusterService,
        protected ClusterVerifierInterface $verifier,
        protected ClusterAuditLoggerInterface $auditLogger
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $scope = strtolower((string) $request->query('scope', 'all'));

        $data = match ($scope) {
            'installers' => ['installers' => $this->verifier->verifyInstallers()],
            'agents' => ['glpi_agents' => $this->verifier->verifyAgents()],
            'parity' => ['runtime_parity' => $this->verifier->verifyParity()],
            default => $this->verifier->verifyAll(),
        };

        $this->auditLogger->logAccess(
            $request,
            'cluster_api_verification',
            "Inspección técnica remota (scope: {$scope}) ejecutada desde " . ($request->ip() ?: 'desconocido'),
            ['scope' => $scope, 'status' => $data['status'] ?? 'ok']
        );

        return response()->json(array_merge([
            'success' => true,
            'node_role' => $this->clusterService->getNodeRole(),
            'hostname' => gethostname(),
            'scope' => $scope,
        ], $data));
    }
}
