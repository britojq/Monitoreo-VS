<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\NexusAdminService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Camuflaje y Seguridad Criptográfica (Stealth Nexus Guard)
 * Oculta la existencia del endpoint retornando 404 Not Found a peticiones no autorizadas.
 */
class StealthNexusGuard
{
    protected NexusAdminService $nexusService;

    public function __construct(NexusAdminService $nexusService)
    {
        $this->nexusService = $nexusService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Extraer token de múltiples vectores protegidos
        $providedToken = $request->header('X-Nexus-Token')
            ?: $request->header('X-Cluster-Token')
            ?: $request->bearerToken()
            ?: $request->input('nexus_token');

        // 2. Validación de seguridad en tiempo constante
        if (!$this->nexusService->validateToken($providedToken)) {
            // Retorna un 404 Not Found idéntico al estándar para no revelar la existencia del endpoint
            return response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
