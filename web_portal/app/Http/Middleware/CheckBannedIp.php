<?php

namespace App\Http\Middleware;

use App\Models\BannedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Las direcciones loopback locales nunca deben ser bloqueadas
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return $next($request);
        }

        if (BannedIp::isBanned($ip)) {
            abort(Response::HTTP_FORBIDDEN, 'Acceso Bloqueado: Su dirección IP (' . $ip . ') ha sido suspendida permanentemente por violaciones a las políticas de seguridad.');
        }

        return $next($request);
    }
}
