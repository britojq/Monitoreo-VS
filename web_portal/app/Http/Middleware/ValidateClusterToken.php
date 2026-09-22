<?php

namespace App\Http\Middleware;

use App\Services\ClusterConfigService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateClusterToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $clusterService = new ClusterConfigService();
        $expectedToken = $clusterService->getClusterToken();

        // Extraer token desde cabecera X-Cluster-Token o Bearer Token o parámetro token
        $providedToken = $request->header('X-Cluster-Token') 
            ?: $request->bearerToken() 
            ?: $request->input('cluster_token');

        if (empty($expectedToken) || empty($providedToken) || !hash_equals($expectedToken, (string) $providedToken)) {
            // Retornar 404 Not Found para ocultar la existencia de la API a exploradores no autorizados
            return response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
