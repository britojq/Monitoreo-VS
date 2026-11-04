<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\ClusterConfigInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateClusterToken
{
    public function __construct(
        protected ClusterConfigInterface $clusterService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = $this->clusterService->getClusterToken();

        // 1. Verificación opcional de IP autorizada para nodos del clúster (SEC-002)
        $allowedClusterIps = (array) config('security.cluster_allowed_ips', []);
        if (!empty($allowedClusterIps)) {
            $clientIp = (string) $request->ip();
            $permitted = array_merge(['127.0.0.1', '::1'], $allowedClusterIps);
            if (!in_array($clientIp, $permitted, true)) {
                return response()->json([
                    'message' => 'Not Found',
                ], Response::HTTP_NOT_FOUND);
            }
        }

        // 2. Extraer token desde cabecera X-Cluster-Token o Bearer Token o parámetro token
        $providedToken = $request->header('X-Cluster-Token') 
            ?: $request->bearerToken() 
            ?: $request->input('cluster_token');

        if (empty($expectedToken) || empty($providedToken) || !hash_equals($expectedToken, (string) $providedToken)) {
            return response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND);
        }

        // 3. Validación de Timestamp Anti-Replay (tolerancia máxima de 300 segundos)
        if ($request->hasHeader('X-Cluster-Timestamp')) {
            $timestamp = (int) $request->header('X-Cluster-Timestamp');
            if (abs(time() - $timestamp) > 300) {
                return response()->json([
                    'message' => 'Timestamp Expired',
                ], Response::HTTP_FORBIDDEN);
            }

            // 4. Validación de Firma HMAC-SHA256 del payload si la cabecera está presente
            if ($request->hasHeader('X-Cluster-Signature')) {
                $providedSig = (string) $request->header('X-Cluster-Signature');
                $payload = (string) $request->getContent();
                $expectedSig = hash_hmac('sha256', "{$timestamp}:{$payload}", $expectedToken);

                if (!hash_equals($expectedSig, $providedSig)) {
                    return response()->json([
                        'message' => 'Invalid Signature',
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        return $next($request);
    }
}
