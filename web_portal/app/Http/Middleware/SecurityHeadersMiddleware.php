<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Inyecta encabezados de seguridad HTTP recomendados por OWASP
     * en todas las respuestas salientes del portal.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Previene ataques de tipo MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Previene ataques de Clickjacking permitiendo renderizado solo desde el mismo origen
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Filtro XSS para navegadores compatibles
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Política de referencia segura
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restricción de APIs del navegador no requeridas en el portal
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');

        // HSTS si la conexión es HTTPS
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
