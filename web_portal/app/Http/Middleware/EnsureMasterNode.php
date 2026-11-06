<?php

namespace App\Http\Middleware;

use App\Contracts\Services\ClusterConfigInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMasterNode
{
    public function __construct(
        protected ClusterConfigInterface $clusterService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing', 'local')) {
            return $next($request);
        }

        if ($this->clusterService->isSlave()) {
            $masterUrl = $this->clusterService->getMasterApiUrl();
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
