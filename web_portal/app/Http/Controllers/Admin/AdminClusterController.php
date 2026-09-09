<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminClusterController extends Controller
{
    protected ClusterConfigService $clusterService;

    public function __construct(ClusterConfigService $clusterService)
    {
        $this->clusterService = $clusterService;
    }

    /**
     * Actualiza la configuración de rol Master/Slave y conexión.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'node_role' => ['required', 'in:master,slave'],
            'cluster_token' => ['required', 'string', 'min:16'],
            'master_api_url' => ['nullable', 'url'],
        ], [
            'node_role.required' => 'Debe seleccionar un rol para el servidor (Master o Slave).',
            'node_role.in' => 'El rol seleccionado no es válido.',
            'cluster_token.required' => 'El token de clúster es obligatorio.',
            'cluster_token.min' => 'El token de clúster debe tener al menos 16 caracteres.',
            'master_api_url.url' => 'La URL del servidor Master debe ser válida (ej: http://10.20.23.252).',
        ]);

        if ($validated['node_role'] === 'slave' && empty($validated['master_api_url'])) {
            return back()->with('error', 'Para operar en modo Esclavo (Slave) debe especificar la URL del servidor Master.');
        }

        $ok = $this->clusterService->updateConfig([
            'node_role' => $validated['node_role'],
            'cluster_token' => $validated['cluster_token'],
            'master_api_url' => $validated['master_api_url'] ?? 'http://10.20.23.252',
            'cluster_last_sync_status' => $validated['node_role'] === 'master' ? 'master_active' : 'configured',
        ]);

        if ($ok) {
            $roleLabel = $validated['node_role'] === 'master' ? 'MASTER (Servidor Principal)' : 'SLAVE (Nodo Réplica)';
            return back()->with('success', "Rol del servidor configurado exitosamente como {$roleLabel}.");
        }

        return back()->with('error', 'Error al guardar la configuración del clúster.');
    }

    /**
     * Comprueba en tiempo real la conectividad con el servidor Master vía API.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $targetUrl = rtrim($request->input('master_api_url') ?: $this->clusterService->getMasterApiUrl(), '/');
        $token = $request->input('cluster_token') ?: $this->clusterService->getClusterToken();

        if (empty($targetUrl) || empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe indicar la URL del Master y el Token de Clúster.',
            ], 422);
        }

        $start = microtime(true);
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Cluster-Token' => $token,
                    'Accept' => 'application/json',
                ])
                ->get("{$targetUrl}/api/cluster/ping");

            $latency = round((microtime(true) - $start) * 1000, 1);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'latency_ms' => $latency,
                    'message' => "¡Conexión exitosa con el Master! ({$latency} ms). Servidor remoto verificado.",
                    'data' => $data,
                ]);
            }

            if ($response->status() === 404) {
                return response()->json([
                    'success' => false,
                    'latency_ms' => $latency,
                    'message' => 'El servidor Master respondió pero el Token de Clúster es inválido o no coincide.',
                ], 401);
            }

            return response()->json([
                'success' => false,
                'latency_ms' => $latency,
                'message' => "Respuesta inesperada del Master (Código HTTP: {$response->status()}).",
            ], $response->status());

        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 1);
            return response()->json([
                'success' => false,
                'latency_ms' => $latency,
                'message' => "Fallo de conexión hacia {$targetUrl}: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Genera un nuevo token criptográfico de alta entropía.
     */
    public function generateToken(): JsonResponse
    {
        $newToken = bin2hex(random_bytes(32));
        return response()->json([
            'success' => true,
            'token' => $newToken,
        ]);
    }
}
