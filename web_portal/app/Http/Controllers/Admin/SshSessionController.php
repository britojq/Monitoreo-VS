<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SshSessionController extends Controller
{
    /**
     * Generar URL para iniciar sesión SSH en terminal web
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
        $targetPort = (int) ($validated['port'] ?? 22);
        $targetName = $validated['name'] ?? 'Dispositivo SSH';
        $targetSite = $validated['site'] ?? 'Red Corporativa';

        $terminalUrl = route('admin.ssh.terminal', [
            'ip' => $targetIp,
            'port' => $targetPort,
            'name' => $targetName,
            'site' => $targetSite,
        ]);

        Log::info(sprintf(
            '[SSH] Sesión web solicitada por %s (%s) para equipo %s (%s:%d) en %s',
            $user->name,
            $user->role,
            $targetName,
            $targetIp,
            $targetPort,
            $targetSite
        ));

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

        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403, 'Acceso denegado. No posee permisos para abrir sesiones de consola SSH.');
        }

        $ip = $request->query('ip', '0.0.0.0');
        $port = (int) $request->query('port', 22);
        $name = $request->query('name', 'Dispositivo SSH');
        $site = $request->query('site', 'Red Corporativa');

        return view('admin.ssh.terminal', compact('ip', 'port', 'name', 'site'));
    }
}
