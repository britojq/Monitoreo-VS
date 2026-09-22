<?php

namespace App\Http\Middleware;

use App\Services\ClusterConfigService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMasterNode
{
    public function handle(Request $request, Closure $next): Response
    {
        $clusterService = new ClusterConfigService();

        if ($clusterService->isSlave()) {
            $masterUrl = $clusterService->getMasterApiUrl();
            $msg = "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de infraestructura deben realizarse en el servidor MASTER ({$masterUrl}).";

            if ($request->expectsJson() || $request->isXmlHttpRequest()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], Response::HTTP_FORBIDDEN);
            }

            return back()->with('error', $msg);
        }

        return $next($request);
    }
}
