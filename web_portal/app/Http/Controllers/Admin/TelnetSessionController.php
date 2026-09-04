<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TelnetSessionController extends Controller
{
    protected string $tokensFile = '/etc/websockify/tokens.cfg';

    /**
     * Generar un token temporal y seguro para iniciar sesión Telnet en terminal web
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
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'name' => ['nullable', 'string', 'max:100'],
            'site' => ['nullable', 'string', 'max:100'],
        ]);

        $targetIp = $validated['ip'];
        $targetPort = (int) ($validated['port'] ?? 23);
        $targetName = $validated['name'] ?? 'Dispositivo de Red';
        $targetSite = $validated['site'] ?? 'Red Valle Seco';

        // Generar token único para sesión Telnet
        $token = 'telnet_' . bin2hex(random_bytes(16));

        // Registrar el token en el archivo de configuración de websockify
        try {
            $this->registerToken($token, $targetIp, $targetPort);
        } catch (\Throwable $e) {
            Log::error('Error registrando token Telnet en websockify: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error de infraestructura al inicializar proxy Telnet: ' . $e->getMessage(),
            ], 500);
        }

        $terminalUrl = route('admin.telnet.terminal', [
            'token' => $token,
            'ip' => $targetIp,
            'port' => $targetPort,
            'name' => $targetName,
            'site' => $targetSite,
        ]);

        Log::info(sprintf(
            '[TELNET] Sesión iniciada por %s (%s) para equipo %s (%s:%d) en %s [Token: %s]',
            $user->name,
            $user->role,
            $targetName,
            $targetIp,
            $targetPort,
            $targetSite,
            $token
        ));

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

        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403, 'Acceso denegado. No posee permisos para abrir sesiones de consola de red.');
        }

        $token = $request->query('token');
        $ip = $request->query('ip', '0.0.0.0');
        $port = (int) $request->query('port', 23);
        $name = $request->query('name', 'Dispositivo');
        $site = $request->query('site', 'Red Valle Seco');

        if (!$token || !$this->isTokenValid($token)) {
            abort(404, 'El token de conexión Telnet ha expirado o no es válido. Genere una nueva sesión desde el panel.');
        }

        return view('admin.telnet.terminal', compact('token', 'ip', 'port', 'name', 'site'));
    }

    /**
     * Registrar token en /etc/websockify/tokens.cfg manteniendo el archivo podado
     */
    protected function registerToken(string $token, string $ip, int $port = 23): void
    {
        $dir = dirname($this->tokensFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $existingLines = [];
        if (file_exists($this->tokensFile)) {
            $rawLines = file($this->tokensFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($rawLines !== false) {
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
