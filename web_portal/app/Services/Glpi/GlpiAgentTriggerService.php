<?php

namespace App\Services\Glpi;

use App\Contracts\GlpiAgentTriggerInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Servicio para disparo y verificación remota de agentes GLPI vía HTTP (puerto 62354).
 * Responsabilidad Única (S): Comunicación con el agente local para sondeos bajo demanda.
 */
class GlpiAgentTriggerService implements GlpiAgentTriggerInterface
{
    protected int $defaultTimeout;

    public function __construct(int $timeout = 3)
    {
        $this->defaultTimeout = $timeout;
    }

    /**
     * Consulta el estado del agente GLPI remoto en el puerto 62354.
     */
    public function checkAgentStatus(string $ip): array
    {
        $cleanIp = trim($ip);
        if (!filter_var($cleanIp, FILTER_VALIDATE_IP)) {
            return [
                'online' => false,
                'status' => 'invalid_ip',
                'message' => "La dirección IP '{$ip}' no es válida.",
            ];
        }

        try {
            $url = "http://{$cleanIp}:62354/status";
            $response = Http::timeout($this->defaultTimeout)
                ->withHeaders(['User-Agent' => 'ValleSeco-ITAM-Controller/1.0'])
                ->get($url);

            if ($response->successful()) {
                return [
                    'online' => true,
                    'status' => 'active',
                    'message' => "Agente GLPI activo y respondiendo en el puerto 62354.",
                    'raw' => $response->body(),
                ];
            }

            return [
                'online' => false,
                'status' => 'http_error',
                'message' => "El agente respondió con código HTTP {$response->status()}.",
            ];
        } catch (Throwable $e) {
            return [
                'online' => false,
                'status' => 'unreachable',
                'message' => "No se pudo contactar al agente en {$cleanIp}:62354. Puerto cerrado o agente detenido.",
            ];
        }
    }

    /**
     * Envía una señal al agente GLPI remoto para que ejecute inmediatamente
     * la recolección de inventario y reporte al servidor (GET http://<ip>:62354/now).
     */
    public function triggerImmediateInventory(string $ip): array
    {
        $cleanIp = trim($ip);
        if (!filter_var($cleanIp, FILTER_VALIDATE_IP)) {
            return [
                'success' => false,
                'message' => "Dirección IP inválida para el sondeo del agente.",
            ];
        }

        try {
            $url = "http://{$cleanIp}:62354/now";
            $response = Http::timeout($this->defaultTimeout)
                ->withHeaders(['User-Agent' => 'ValleSeco-ITAM-Controller/1.0'])
                ->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'message' => "Orden de inventario enviada exitosamente al equipo ({$cleanIp}). El agente está recolectando telemetría de hardware.",
                ];
            }

            return [
                'success' => false,
                'status_code' => $response->status(),
                'message' => "El agente GLPI en {$cleanIp} respondió con error HTTP {$response->status()}.",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "No se pudo enviar la orden al agente en {$cleanIp}:62354. Verifique que el servicio esté activo y el cortafuegos local permita la conexión.",
            ];
        }
    }
}
