<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\ClusterConfigInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // 2. Extraer token estrictamente desde cabecera X-Cluster-Token o Bearer Token (F-09: se erradica query param)
        $providedToken = $request->header('X-Cluster-Token') 
            ?: $request->bearerToken();

        if (empty($expectedToken) || empty($providedToken) || !hash_equals($expectedToken, (string) $providedToken)) {
            return response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND);
        }

        // 3. Validación Fail-Closed de Cabeceras Anti-Replay Obligatorias (F-09)
        if (!$request->hasHeader('X-Cluster-Timestamp') || !$request->hasHeader('X-Cluster-Signature')) {
            return response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND);
        }

        // 4. Validación de Timestamp Anti-Replay (tolerancia máxima de 300 segundos)
        $timestamp = (int) $request->header('X-Cluster-Timestamp');
        if (abs(time() - $timestamp) > 300) {
            return response()->json([
                'message' => 'Timestamp Expired',
            ], Response::HTTP_FORBIDDEN);
        }

        // 5. Validación de Firma HMAC-SHA256 del payload
        $providedSig = (string) $request->header('X-Cluster-Signature');
        $payload = (string) $request->getContent();
        if ($request->isMethod('GET') && $payload === '[]') {
            $payload = '';
        }
        $expectedSig = hash_hmac('sha256', "{$timestamp}:{$payload}", $expectedToken);

        if (!hash_equals($expectedSig, $providedSig)) {
            return response()->json([
                'message' => 'Invalid Signature',
            ], Response::HTTP_FORBIDDEN);
        }

        // 6. Detección y Prevención de Replay Attack mediante Caché de Firmas (TTL 300 segundos)
        $sigKey = 'cluster_sig_' . hash('sha256', $providedSig);
        if (Cache::has($sigKey)) {
            return response()->json([
                'message' => 'Signature Already Used',
            ], Response::HTTP_FORBIDDEN);
        }
        Cache::put($sigKey, true, 300);

        return $next($request);
    }
}
