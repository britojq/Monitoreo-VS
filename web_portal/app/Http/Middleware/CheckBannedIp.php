<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BannedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedIp
{
    /**
     * Obtiene la lista blanca de IPs autorizadas e inmunes a bloqueos desde la configuración.
     * Cumple con OWASP A05:2021 externalizando direcciones corporativas a variables de entorno.
     *
     * @return array<int, string>
     */
    protected function getWhitelistIps(): array
    {
        /** @var array<int, string> $ips */
        $ips = config('security.whitelist_ips', ['127.0.0.1', '::1']);
        return is_array($ips) ? $ips : ['127.0.0.1', '::1'];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();
        $whitelist = $this->getWhitelistIps();

        // Las direcciones de la lista blanca y del loopback local nunca deben ser bloqueadas
        if (in_array($ip, $whitelist, true) || str_starts_with($ip, '127.')) {
            return $next($request);
        }

        if (BannedIp::isBanned($ip)) {
            abort(
                Response::HTTP_FORBIDDEN,
                'Acceso Bloqueado: Su dirección IP (' . $ip . ') ha sido suspendida permanentemente por violaciones a las políticas de seguridad.'
            );
        }

        return $next($request);
    }
}
