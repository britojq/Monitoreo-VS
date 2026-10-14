<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RemoteAccess\WebsockifyTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SshSessionController extends Controller
{
    public function __construct(
        protected WebsockifyTokenService $tokenService
    ) {
    }

    /**
     * Generar URL para iniciar sesión SSH en terminal web y auditar en MariaDB
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->hasPermission('remote.ssh'))) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Su cuenta no dispone del permiso requerido [remote.ssh].',
            ], 403);
        }

        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'name' => ['nullable', 'string', 'max:100'],
            'site' => ['nullable', 'string', 'max:100'],
        ]);

        $targetIp = $validated['ip'];
        $targetPort = (int) ($validated['port'] ?? 22);
        $targetName = $validated['name'] ?? 'Dispositivo SSH';
        $targetSite = $validated['site'] ?? 'Red Corporativa';

        $terminalUrl = route('admin.ssh.terminal', [
            'ip' => $targetIp,
            'port' => $targetPort,
            'name' => $targetName,
            'site' => $targetSite,
        ]);

        // Registrar auditoría en MariaDB SSOT
        $this->tokenService->auditSession(
            protocol: 'ssh',
            user: $user,
            targetIp: $targetIp,
            targetPort: $targetPort,
            targetName: $targetName,
            targetSite: $targetSite,
            token: null,
            request: $request
        );

        return response()->json([
            'success' => true,
            'ip' => $targetIp,
            'port' => $targetPort,
            'name' => $targetName,
            'site' => $targetSite,
            'viewer_url' => $terminalUrl,
        ]);
    }

    /**
     * Desplegar la vista de terminal SSH web interactiva
     */
    public function terminal(Request $request): View
    {
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->hasPermission('remote.ssh'))) {
            abort(403, 'Acceso denegado. No posee el permiso [remote.ssh] para abrir sesiones de consola SSH.');
        }

        $ip = (string) $request->query('ip', '0.0.0.0');
        $port = (int) $request->query('port', 22);
        $name = (string) $request->query('name', 'Dispositivo SSH');
        $site = (string) $request->query('site', 'Red Corporativa');

        return view('admin.ssh.terminal', compact('ip', 'port', 'name', 'site'));
    }
}
