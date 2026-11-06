<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RemoteAccess\WebsockifyTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TelnetSessionController extends Controller
{
    public function __construct(
        protected WebsockifyTokenService $tokenService
    ) {
    }

    /**
     * Generar un token temporal y seguro para iniciar sesión Telnet en terminal web
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->hasPermission('remote.telnet'))) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Su cuenta no dispone del permiso requerido [remote.telnet].',
            ], 403);
        }

        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'name' => ['nullable', 'string', 'max:100'],
            'site' => ['nullable', 'string', 'max:100'],
        ]);

        $targetIp = $validated['ip'];
        $targetPort = (int) ($validated['port'] ?? 23);
        $targetName = $validated['name'] ?? 'Dispositivo de Red';
        $targetSite = $validated['site'] ?? 'Red Valle Seco';

        // Generar token único para sesión Telnet
        $token = $this->tokenService->generateToken('telnet');

        // Registrar el token en el archivo de configuración de websockify
        try {
            $this->tokenService->registerToken($token, $targetIp, $targetPort);
        } catch (\Throwable $e) {
            Log::error('Error registrando token Telnet en websockify: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error de infraestructura al inicializar proxy Telnet: ' . $e->getMessage(),
            ], 500);
        }

        // Registrar auditoría en MariaDB SSOT
        $this->tokenService->auditSession(
            protocol: 'telnet',
            user: $user,
            targetIp: $targetIp,
            targetPort: $targetPort,
            targetName: $targetName,
            targetSite: $targetSite,
            token: $token,
            request: $request
        );

        $terminalUrl = route('admin.telnet.terminal', [
            'token' => $token,
            'ip' => $targetIp,
            'port' => $targetPort,
            'name' => $targetName,
            'site' => $targetSite,
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'ip' => $targetIp,
            'port' => $targetPort,
            'name' => $targetName,
            'site' => $targetSite,
            'viewer_url' => $terminalUrl,
        ]);
    }

    /**
     * Desplegar la vista de terminal web interactiva con xterm.js
     */
    public function terminal(Request $request): View
    {
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->hasPermission('remote.telnet'))) {
            abort(403, 'Acceso denegado. No posee el permiso [remote.telnet] para abrir sesiones de consola de red.');
        }

        $token = (string) $request->query('token', '');
        $ip = (string) $request->query('ip', '0.0.0.0');
        $port = (int) $request->query('port', 23);
        $name = (string) $request->query('name', 'Dispositivo');
        $site = (string) $request->query('site', 'Red Valle Seco');

        if (empty($token) || !$this->tokenService->isTokenValid($token)) {
            abort(404, 'El token de conexión Telnet ha expirado o no es válido. Genere una nueva sesión desde el panel.');
        }

        return view('admin.telnet.terminal', compact('token', 'ip', 'port', 'name', 'site'));
    }
}
