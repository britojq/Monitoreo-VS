<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para blindar endpoints de telemetría y sondeo periódico en segundo plano.
 * Previene el Error #6 de errores-cometidos.md:
 * - Evita que peticiones AJAX/Live contaminen session('url.intended').
 * - Si la sesión expira en segundo plano, responde con 401 JSON en lugar de una redirección corrupta.
 */
class EnsureStatelessTelemetry
{
    /**
     * Rutas o patrones de telemetría que jamás deben persistirse como url.intended
     */
    protected array $volatilePatterns = [
        '/live',
        '/quick-status',
        '/top-talkers',
        '/telemetry',
        '/ajax',
        '/status',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Limpieza preventiva: si la sesión ya traía una url.intended volátil, purgarla
        if ($request->hasSession()) {
            $intended = $request->session()->get('url.intended');
            if ($intended && $this->isVolatileUrl($intended)) {
                $request->session()->forget('url.intended');
            }
        }

        // 2. Si la ruta actual coincide con telemetría/sondeo volátil
        $isTelemetryEndpoint = $this->isVolatilePath($request->path());

        if ($isTelemetryEndpoint && !auth()->check()) {
            if ($request->hasSession()) {
                $request->session()->forget('url.intended');
            }

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'unauthenticated' => true,
                    'error' => 'Unauthenticated',
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->route('login');
        }

        $response = $next($request);

        // 3. Post-procesamiento: asegurar que la sesión no guardó esta URL como destino
        if ($request->hasSession()) {
            $intended = $request->session()->get('url.intended');
            if ($intended && $this->isVolatileUrl($intended)) {
                $request->session()->forget('url.intended');
            }
        }

        return $response;
    }

    /**
     * Determina si una URL completa pertenece a patrones de telemetría volátil.
     */
    protected function isVolatileUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        return $this->isVolatilePath($path);
    }

    /**
     * Determina si un path relativo pertenece a patrones de telemetría volátil.
     */
    protected function isVolatilePath(string $path): bool
    {
        $normalized = '/' . ltrim($path, '/');

        // Endpoints públicos legítimos que no requieren autenticación
        if ($normalized === '/api/status' || $normalized === '/' || str_starts_with($normalized, '/acta/verificar')) {
            return false;
        }

        foreach ($this->volatilePatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
