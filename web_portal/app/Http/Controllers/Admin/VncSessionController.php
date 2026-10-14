<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryAsset;
use App\Services\RemoteAccess\WebsockifyTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VncSessionController extends Controller
{
    public function __construct(
        protected WebsockifyTokenService $tokenService
    ) {
    }

    /**
     * Generar un token temporal y seguro para iniciar sesión VNC
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->hasPermission('remote.vnc'))) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Su cuenta no dispone del permiso requerido [remote.vnc].',
            ], 403);
        }

        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'name' => ['nullable', 'string', 'max:100'],
            'site' => ['nullable', 'string', 'max:100'],
        ]);

        $targetIp = $validated['ip'];
        $targetName = $validated['name'] ?? 'Estación de Trabajo';
        $targetSite = $validated['site'] ?? 'Sede Regional';

        $asset = InventoryAsset::where('ip_address', $targetIp)->first();
        $targetPort = (int) ($validated['port'] ?? ($asset?->vnc_port ?: 5900));

        // Generar un token aleatorio criptográficamente seguro
        $token = $this->tokenService->generateToken('vnc');

        // Registrar el token en el archivo de configuración de websockify
        try {
            $this->tokenService->registerToken($token, $targetIp, $targetPort);
        } catch (\Throwable $e) {
            Log::error('Error registrando token VNC en websockify: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error de infraestructura al inicializar proxy VNC: ' . $e->getMessage(),
            ], 500);
        }

        // Registrar auditoría en MariaDB SSOT
        $this->tokenService->auditSession(
            protocol: 'vnc',
            user: $user,
            targetIp: $targetIp,
            targetPort: $targetPort,
            targetName: $targetName,
            targetSite: $targetSite,
            token: $token,
            request: $request
        );

        $viewerUrl = route('admin.vnc.viewer', [
            'token' => $token,
            'ip' => $targetIp,
            'name' => $targetName,
            'site' => $targetSite,
        ]);

        $novncPath = 'websockify?token=' . $token;
        $passwordParam = ($asset && !empty($asset->vnc_password_encrypted)) ? ('&password=' . urlencode($asset->vnc_password_encrypted)) : '';
        $novncDirectUrl = '/novnc/vnc.html?autoconnect=true&resize=scale&reconnect=true&path=' . urlencode($novncPath) . $passwordParam;

        return response()->json([
            'success' => true,
            'token' => $token,
            'ip' => $targetIp,
            'port' => $targetPort,
            'name' => $targetName,
            'site' => $targetSite,
            'viewer_url' => $viewerUrl,
            'novnc_url' => $novncDirectUrl,
        ]);
    }

    /**
     * Desplegar la vista del visor web integrado noVNC
     */
    public function viewer(Request $request): View
    {
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->hasPermission('remote.vnc'))) {
            abort(403, 'Acceso denegado. No posee el permiso [remote.vnc] para visualizar escritorios remotos.');
        }

        $token = (string) $request->query('token', '');
        $ip = (string) $request->query('ip', '0.0.0.0');
        $name = (string) $request->query('name', 'Estación Remota');
        $site = (string) $request->query('site', 'Sede Regional');

        if (empty($token) || !$this->tokenService->isTokenValid($token)) {
            abort(404, 'El token de conexión VNC ha expirado o no es válido. Genere una nueva sesión desde el panel de monitoreo.');
        }

        $asset = InventoryAsset::where('ip_address', $ip)->first();
        $passwordParam = ($asset && !empty($asset->vnc_password_encrypted)) ? ('&password=' . urlencode($asset->vnc_password_encrypted)) : '';
        $novncSrc = '/novnc/vnc.html?autoconnect=true&resize=scale&reconnect=true&path=' . urlencode('websockify?token=' . $token) . $passwordParam;

        return view('admin.vnc.viewer', compact('token', 'ip', 'name', 'site', 'novncSrc'));
    }
}
