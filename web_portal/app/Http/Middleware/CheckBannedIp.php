<?php

namespace App\Http\Middleware;

use App\Models\BannedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedIp
{
    /**
     * Lista blanca de IPs inmunes a bloqueos (Loopback, Servidor de Desarrollo, Servidor Master y Gateway)
     */
    protected const WHITELIST_IPS = [
        '127.0.0.1',
        '::1',
        '192.168.1.220', // Servidor de Desarrollo (Esclavo)
        '192.168.1.250', // Servidor de Producción (Master)
        '192.168.1.1',   // Gateway Corporativo
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Las direcciones de la lista blanca y del clúster de administración nunca deben ser bloqueadas
        if (in_array($ip, self::WHITELIST_IPS, true) || str_starts_with($ip, '127.') || str_starts_with($ip, '192.168.1.')) {
            return $next($request);
        }

        if (BannedIp::isBanned($ip)) {
            abort(Response::HTTP_FORBIDDEN, 'Acceso Bloqueado: Su dirección IP (' . $ip . ') ha sido suspendida permanentemente por violaciones a las políticas de seguridad.');
        }

        return $next($request);
    }
}
