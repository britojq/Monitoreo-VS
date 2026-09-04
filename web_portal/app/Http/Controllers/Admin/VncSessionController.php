<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VncSessionController extends Controller
{
    protected string $tokensFile = '/etc/websockify/tokens.cfg';

    /**
     * Generar un token temporal y seguro para iniciar sesión VNC
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Se requiere rol de Operador o Administrador.',
            ], 403);
        }

        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'name' => ['nullable', 'string', 'max:100'],
            'site' => ['nullable', 'string', 'max:100'],
        ]);

        $targetIp = $validated['ip'];
        $targetName = $validated['name'] ?? 'Estación de Trabajo';
        $targetSite = $validated['site'] ?? 'Sede Regional';

        // Generar un token aleatorio criptográficamente seguro
        $token = 'vnc_' . bin2hex(random_bytes(16));

        // Registrar el token en el archivo de configuración de websockify
        try {
            $this->registerToken($token, $targetIp, 5900);
        } catch (\Throwable $e) {
            Log::error('Error registrando token VNC en websockify: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error de infraestructura al inicializar proxy VNC: ' . $e->getMessage(),
            ], 500);
        }

        $viewerUrl = route('admin.vnc.viewer', [
            'token' => $token,
            'ip' => $targetIp,
            'name' => $targetName,
            'site' => $targetSite,
        ]);

        $novncPath = 'websockify?token=' . $token;
        $novncDirectUrl = '/novnc/vnc.html?autoconnect=true&resize=scale&reconnect=true&path=' . urlencode($novncPath);

        Log::info(sprintf(
            '[VNC] Sesión creada por %s (%s) para equipo %s (%s) en %s [Token: %s]',
            $user->name,
            $user->role,
            $targetName,
            $targetIp,
            $targetSite,
            $token
        ));

        return response()->json([
            'success' => true,
            'token' => $token,
            'ip' => $targetIp,
            'port' => 5900,
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

        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403, 'Acceso denegado. No posee permisos para visualizar escritorios remotos.');
        }

        $token = $request->query('token');
        $ip = $request->query('ip', '0.0.0.0');
        $name = $request->query('name', 'Estación Remota');
        $site = $request->query('site', 'Sede Regional');

        if (!$token || !$this->isTokenValid($token)) {
            abort(404, 'El token de conexión VNC ha expirado o no es válido. Genere una nueva sesión desde el panel de monitoreo.');
        }

        $novncSrc = '/novnc/vnc.html?autoconnect=true&resize=scale&reconnect=true&path=' . urlencode('websockify?token=' . $token);

        return view('admin.vnc.viewer', compact('token', 'ip', 'name', 'site', 'novncSrc'));
    }

    /**
     * Registrar token en /etc/websockify/tokens.cfg manteniendo el archivo podado
     */
    protected function registerToken(string $token, string $ip, int $port = 5900): void
    {
        $dir = dirname($this->tokensFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $existingLines = [];
        if (file_exists($this->tokensFile)) {
            $rawLines = file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($rawLines !== false) {
                // Conservar como máximo los últimos 100 registros para evitar crecimiento indefinido
                $existingLines = array_slice($rawLines, -100);
            }
        }

        $newLine = "{$token}: {$ip}:{$port}";
        $existingLines[] = $newLine;

        $content = implode("\n", $existingLines) . "\n";
        file_put_contents($this->tokensFile, $content, LOCK_EX);
    }

    /**
     * Verificar si el token existe en el archivo
     */
    protected function isTokenValid(string $token): bool
    {
        if (!file_exists($this->tokensFile)) {
            return false;
        }

        $lines = file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, $token . ':')) {
                return true;
            }
        }

        return false;
    }
}
