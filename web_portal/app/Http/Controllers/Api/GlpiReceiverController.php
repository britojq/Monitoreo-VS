<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GlpiInventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GlpiReceiverController extends Controller
{
    protected GlpiInventoryService $glpiService;

    public function __construct(GlpiInventoryService $glpiService)
    {
        $this->glpiService = $glpiService;
    }

    /**
     * Endpoint receptor para agentes GLPI (POST y GET).
     * Ruta: /api/v1/inventory/receiver
     */
    public function handle(Request $request): Response
    {
        // 1. Responder a peticiones GET de sondeo / verificación de conectividad
        if ($request->isMethod('get')) {
            return response(
                json_encode([
                    'status' => 'ok',
                    'service' => 'Valle Seco ITAM GLPI Receiver',
                    'version' => '1.0.0',
                    'timestamp' => now()->toIso8601String(),
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        // 2. Validación de Token Criptográfico (si está configurado)
        $expectedToken = config('inventory.glpi_token');
        if (!empty($expectedToken)) {
            $sentToken = $request->header('X-Glpi-Agent-Token')
                ?? $request->bearerToken()
                ?? $request->route('token')
                ?? $request->query('token')
                ?? $request->header('X-Agent-Token');

            if ($sentToken !== $expectedToken) {
                return response(
                    json_encode([
                        'status' => 'error',
                        'message' => 'Token criptográfico de agente GLPI inválido o ausente',
                    ]),
                    401,
                    ['Content-Type' => 'application/json']
                );
            }
        }

        // 3. Obtener carga cruda
        $rawPayload = $request->getContent();
        if (empty($rawPayload)) {
            return response(
                json_encode(['status' => 'error', 'message' => 'Carga de datos vacía']),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        // 4. Procesar en el servicio normalizador
        $result = $this->glpiService->processRawPayload(
            rawContent: $rawPayload,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            contentEncoding: $request->header('Content-Encoding')
        );

        // 5. Retornar respuesta adaptativa (XML para agentes tradicionales OCS/GLPI o JSON)
        $acceptsXml = str_contains((string)$request->header('Accept'), 'xml')
            || str_contains((string)$request->header('Content-Type'), 'xml')
            || str_contains((string)$rawPayload, '<?xml');

        if ($acceptsXml) {
            $xmlResponse = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<REPLY>\n  <RESPONSE>SEND</RESPONSE>\n  <PROLOG_FREQ>24</PROLOG_FREQ>\n  <STATUS>" . ($result['status'] === 'ok' ? 'OK' : 'ERROR') . "</STATUS>\n</REPLY>";
            return response($xmlResponse, 200, ['Content-Type' => 'application/xml']);
        }

        // Si es handshake inicial (prolog/getConfig/contact) en JSON, responder parámetros según especificación oficial GLPI Agent
        if (($result['type'] ?? null) === 'prolog') {
            return response(
                json_encode([
                    'status' => 'ok',
                    'expiration' => '1d',
                    'tasks' => [
                        'inventory' => [
                            'server' => 'glpi',
                            'version' => '10.0.0',
                        ],
                    ],
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        return response(
            json_encode($result),
            $result['status'] === 'ok' ? 200 : 422,
            ['Content-Type' => 'application/json']
        );
    }
}
