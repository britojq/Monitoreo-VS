<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Contracts\OllamaServiceInterface;
use App\Contracts\ToolExecutorInterface;
use App\Contracts\ToolIntentResolverInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Servicio Orquestador de Tool Calling con el motor local de IA (Servidor MCP).
 * Principio de Responsabilidad Única (SRP) y Extensión Limpia (OCP).
 *
 * Administra el ciclo de vida de inferencia:
 * 1. Envío de Prompt del Sistema y Catálogo de Herramientas JSON a Ollama.
 * 2. Detección e interceptación de llamadas a herramientas ('tool_calls').
 * 3. Ejecución segura en el backend Laravel a través de ToolExecutorInterface.
 * 4. Reinyección del resultado JSON en 'role: tool' para síntesis en lenguaje natural.
 */
class OllamaService implements OllamaServiceInterface
{
    protected string $url;
    protected string $model;
    protected int $timeout;
    protected ?ToolIntentResolverInterface $intentResolver;

    public function __construct(
        protected ToolExecutorInterface $toolExecutor,
        ?ToolIntentResolverInterface $intentResolver = null
    ) {
        $this->intentResolver = $intentResolver ?? (app()->bound(ToolIntentResolverInterface::class) ? app(ToolIntentResolverInterface::class) : null);
        $this->url = (string) config('services.ollama.url', 'http://127.0.0.1:11434/api/chat');
        $this->model = (string) config('services.ollama.model', 'qwen-empresa');
        $this->timeout = (int) config('services.ollama.timeout', 180);
    }

    /**
     * Orquesta el bucle conversacional completo con soporte nativo de Tool Calling.
     */
    public function chat(
        string $userMessage,
        array $history = [],
        ?string $userName = null,
        ?string $userRole = null
    ): array {
        $cleanUserMessage = trim($userMessage);
        if (empty($cleanUserMessage)) {
            return [
                'success' => false,
                'reply' => '',
                'tool_executed' => null,
                'tool_result' => null,
                'error' => 'El mensaje del usuario no puede estar vacío.',
            ];
        }

        // 1. Construir lista de mensajes con System Prompt y delimitación de seguridad
        $messages = [];
        $messages[] = [
            'role' => 'system',
            'content' => $this->getSystemPrompt($userName, $userRole),
        ];

        // 2. Sanitizar e incorporar historial previo (descartando respuestas de error o rechazo)
        $isRefusal = fn(string $t) => Str::contains(Str::lower($t), [
            'no tengo capacidad', 'no puedo responder', 'declino responder',
            'lo siento, el motor', 'ocurrió un error', 'no pude conectar',
        ]);

        foreach ($history as $item) {
            $role = $item['role'] ?? 'user';
            $content = $item['content'] ?? '';
            if ($role === 'system' || $isRefusal($content)) {
                continue;
            }
            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        // 3. Añadir el mensaje actual del usuario (sanitizando datos sensibles básicos)
        $sanitizedUserMessage = $this->sanitizeInput($cleanUserMessage);
        $messages[] = [
            'role' => 'user',
            'content' => $sanitizedUserMessage,
        ];

        // 4. Primera llamada a Ollama con el esquema de herramientas (desactivado para Grupo Corporativo)
        $isGroup = ($userRole === 'Grupo Corporativo');
        $tools = !$isGroup ? $this->getToolSchema() : [];

        $postData = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'keep_alive' => '24h',
            'options' => [
                'temperature' => 0.1,
                'num_ctx' => 4096,
            ],
        ];

        if (!empty($tools)) {
            $postData['tools'] = $tools;
        }

        try {
            $response = Http::timeout($this->timeout)->post($this->url, $postData);

            if (!$response->successful()) {
                Log::error('MCP Ollama HTTP Error (Llamada inicial)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'reply' => '',
                    'tool_executed' => null,
                    'tool_result' => null,
                    'error' => 'El servicio local de IA reportó un error al procesar la solicitud.',
                ];
            }

            $responseMessage = $response->json('message', []);
            $toolCalls = $responseMessage['tool_calls'] ?? [];

            // 5. Manejo del Tool Calling si el modelo decidió invocar una herramienta
            if (!empty($toolCalls)) {
                $lastToolName = null;
                $lastToolResult = null;

                // Añadir el mensaje de asistencia con tool_calls al hilo conversacional
                $messages[] = $responseMessage;

                foreach ($toolCalls as $call) {
                    $func = $call['function'] ?? [];
                    $toolName = (string) ($func['name'] ?? '');
                    $toolArgs = (array) ($func['arguments'] ?? []);

                    Log::info('MCP Tool Calling invocado por la IA', [
                        'tool' => $toolName,
                        'arguments' => $toolArgs,
                    ]);

                    // Ejecución en el backend a través de ToolExecutorInterface
                    $toolResult = $this->toolExecutor->executeTool($toolName, $toolArgs);
                    $lastToolName = $toolName;
                    $lastToolResult = $toolResult;

                    // Inyección del resultado en role: tool para que el modelo redacte en lenguaje natural
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    ];
                }

                // 6. Segunda llamada a Ollama para síntesis final
                $secondResponse = Http::timeout($this->timeout)->post($this->url, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                    'keep_alive' => '24h',
                    'options' => [
                        'temperature' => 0.1,
                        'num_ctx' => 4096,
                    ],
                ]);

                if ($secondResponse->successful()) {
                    $finalReply = trim((string) $secondResponse->json('message.content', ''));
                    if (empty($finalReply)) {
                        $finalReply = 'No se obtuvo una respuesta válida del motor de IA tras procesar los datos de la herramienta.';
                    }

                    return [
                        'success' => true,
                        'reply' => $this->sanitizeOutput($finalReply),
                        'tool_executed' => $lastToolName,
                        'tool_result' => $lastToolResult,
                        'error' => null,
                    ];
                }

                Log::error('MCP Ollama HTTP Error (Segunda llamada de síntesis)', [
                    'status' => $secondResponse->status(),
                    'body' => $secondResponse->body(),
                ]);
                return [
                    'success' => false,
                    'reply' => '',
                    'tool_executed' => $lastToolName,
                    'tool_result' => $lastToolResult,
                    'error' => 'Error al sintetizar los datos de la herramienta con el motor local de IA.',
                ];
            }

            // 7. Si Ollama no emitió 'tool_calls' formalmente y no es Grupo Corporativo, evaluar intención
            $directReply = trim((string) ($responseMessage['content'] ?? ''));
            $fallbackTool = (!$isGroup && $this->intentResolver)
                ? $this->intentResolver->resolveIntent($sanitizedUserMessage, $directReply)
                : null;

            if ($fallbackTool !== null) {
                $toolName = $fallbackTool['name'];
                $toolArgs = $fallbackTool['arguments'];

                Log::info('MCP Tool Calling activado por fallback de intención', [
                    'tool' => $toolName,
                    'arguments' => $toolArgs,
                ]);

                $toolResult = $this->toolExecutor->executeTool($toolName, $toolArgs);

                $messages[] = [
                    'role' => 'assistant',
                    'content' => "Consultando datos de infraestructura ({$toolName})...",
                ];
                $messages[] = [
                    'role' => 'tool',
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];

                $secondResponse = Http::timeout($this->timeout)->post($this->url, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                    'keep_alive' => '24h',
                    'options' => [
                        'temperature' => 0.1,
                        'num_ctx' => 4096,
                    ],
                ]);

                if ($secondResponse->successful()) {
                    $finalReply = trim((string) $secondResponse->json('message.content', ''));
                    if (!empty($finalReply)) {
                        return [
                            'success' => true,
                            'reply' => $this->sanitizeOutput($finalReply),
                            'tool_executed' => $toolName,
                            'tool_result' => $toolResult,
                            'error' => null,
                        ];
                    }
                }
            }

            if (empty($directReply)) {
                $directReply = 'No se obtuvo respuesta del motor de IA.';
            }

            return [
                'success' => true,
                'reply' => $this->sanitizeOutput($directReply),
                'tool_executed' => null,
                'tool_result' => null,
                'error' => null,
            ];

        } catch (ConnectionException $e) {
            Log::error('MCP Ollama Connection Exception', ['error' => $e->getMessage()]);
            $isTimeout = str_contains(strtolower($e->getMessage()), 'timeout') || str_contains(strtolower($e->getMessage()), 'timed out');
            $msg = $isTimeout
                ? 'El servicio local de IA tardó más tiempo del esperado en responder. Por favor reintenta en unos momentos.'
                : 'No fue posible conectar con el motor local de IA. Por favor verifica que el servicio esté activo.';

            return [
                'success' => false,
                'reply' => '',
                'tool_executed' => null,
                'tool_result' => null,
                'error' => $msg,
            ];
        } catch (\Throwable $e) {
            Log::error('MCP Ollama Unexpected Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'reply' => '',
                'tool_executed' => null,
                'tool_result' => null,
                'error' => 'Ocurrió un error inesperado al procesar la consulta en el asistente corporativo.',
            ];
        }
    }

    /**
     * Retorna el System Prompt institucional con blindaje de la Regla de Oro #2.
     */
    public function getSystemPrompt(?string $userName = null, ?string $userRole = null): string
    {
        $isGroup = ($userRole === 'Grupo Corporativo');
        if (!$isGroup) {
            $userTag = $userName ? "Usuario: {$userName}" . ($userRole ? " ({$userRole})" : '') : 'Administrador / Owner';

            return <<<EOT
Eres Monitor Valle Seco, Asistente Técnico y NOC/ITAM de la Sede Valle Seco.
Contexto de Interlocutor: {$userTag}. Estás interactuando en comunicación directa con el personal autorizado / Administrador (IDC: 38914901).
Misión: Diagnóstico fáctico y consulta en tiempo real del almacén de datos corporativo, activos ITAM, redes, proxies y telemetría de respaldo eléctrico.
Estilo: Técnico, directo, conciso, formal en español, con viñetas. Máximo 1-2 emojis (⚡, 🖥️, 🌐). Cero rodeos diplomáticos.

REGLAS ESTRICTAS DE RESPUESTA:
1. Conciencia de Autoridad: Hablas con el Administrador / personal técnico autorizado. NUNCA le pidas su nombre, credenciales ni motivos para consultar.
2. Cero Alucinación: Jamás inventes datos ni asumas cantidades. Si te preguntan por totales o consolidados de equipos, invoca OBLIGATORIAMENTE get_inventory_summary.
3. Uso Preciso de Herramientas:
   - Para conteo total, consolidados o inventario por sede/categoría: usa get_inventory_summary.
   - Para un equipo específico, serial, IP, responsable o acta: usa search_itam_asset.
   - Para telemetría y baterías del UPS: usa get_ups_telemetry.
   - Para estado de enlaces de sedes: usa get_noc_site_status.
   - Para estado de proxies pfSense: usa get_proxy_status.
4. Confidencialidad (Regla #2): Jamás menciones nombres de motores internos, marcas ni tecnologías subyacentes. Refiérete siempre a "registros oficiales", "inventario ITAM" o "telemetría NOC".
5. Si no hay parámetros para buscar un equipo específico o te piden ver todo/consolidado, usa get_inventory_summary.
EOT;
        }

        // Contexto para Grupo Corporativo o usuarios no administradores (Asistente Técnico Consultivo Puro)
        return <<<EOT
Eres Monitor Valle Seco, Asistente Técnico Consultivo de Tecnologías de la Información.
Contexto: Interacción en Grupo Corporativo.
Misión: Asistir en dudas técnicas generales de infraestructura, sistemas operativos (Linux, Windows), redes, protocolos de comunicación, comandos y diagnóstico técnico.
Estilo: Técnico, profesional, didáctico, conciso y formal en español.

REGLAS ESTRICTAS:
1. Confidencialidad Absoluta de Infraestructura: Tienes TERMINANTEMENTE PROHIBIDO divulgar o responder sobre datos internos del sistema (inventario de la sede, cantidades de equipos, actas de asignación, seriales, telemetría de UPS o topología interna).
2. Si un usuario en el grupo te solicita información de inventario, cuántos equipos hay, actas o estado interno de la sede, responde con cortesía:
   "La información sobre el inventario, activos y telemetría interna del sistema es confidencial y de acceso exclusivo para el Administrador del Centro de Operaciones a través de su canal privado."
3. Prohibición de Tecnologías Internas (Regla #2): Jamás menciones nombres de motores internos ni bases de datos subyacentes.
EOT;
    }

    /**
     * Retorna el esquema JSON de herramientas compatibles con Ollama /api/chat.
     */
    public function getToolSchema(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_inventory_summary',
                    'description' => 'Obtiene el resumen consolidado y conteo exacto de activos en inventario ITAM por tipo y sede.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'site_name' => [
                                'type' => 'string',
                                'description' => "Nombre o filtro opcional de la sede (ej. 'Valle Seco', 'Consolidado'). Dejar vacío para consolidado global.",
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_ups_telemetry',
                    'description' => 'Telemetría del UPS ZTG 6kVA (voltaje, carga, batería, alarmas).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'ups_id' => [
                                'type' => 'string',
                                'description' => "Serial o ID del UPS (def: 'UPS-DEMO-01').",
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_itam_asset',
                    'description' => 'Busca en inventario ITAM por IP, serial, puesto, responsable o acta.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'IP, serial, puesto o responsable. Opcional.',
                            ],
                            'filter_type' => [
                                'type' => 'string',
                                'enum' => ['all', 'workstation', 'switch', 'router', 'server'],
                                'description' => 'Filtro opcional por tipo.',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_noc_site_status',
                    'description' => 'Estado y latencia de sedes del NOC. Omitir site_name para todas.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'site_name' => [
                                'type' => 'string',
                                'description' => "Nombre de la sede (ej. 'Valle Seco').",
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_proxy_status',
                    'description' => 'Estado y latencia de proxies pfSense. Omitir proxy_id para todos.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'proxy_id' => [
                                'type' => 'string',
                                'description' => 'Nombre o IP del proxy.',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Sanitización de datos sensibles en la entrada del usuario.
     */
    protected function sanitizeInput(string $text): string
    {
        // Ocultar números de cédula venezolana
        $text = preg_replace('/\b[VvEe]?[- ]?(\d{7,8})\b/', '[C.I. OCULTA]', $text);
        // Ocultar números telefónicos de 10-11 dígitos
        $text = preg_replace('/\b(04\d{2}|4\d{2})[- ]?\d{7}\b/', '[TLF. OCULTO]', $text);
        return $text;
    }

    /**
     * Validador y filtro de salida (Output Guardrail) para cumplimiento de la Regla de Oro #2.
     * Erradica de forma determinista cualquier mención accidental a tecnologías o motores subyacentes.
     */
    protected function sanitizeOutput(string $text): string
    {
        $replacements = [
            '/\b(ollama|ollama-server|ollama_model)\b/i' => 'motor local de IA',
            '/\b(mariadb|mysql|innodb)\b/i' => 'base de datos corporativa',
            '/\b(sqlite|postgresql|postgres)\b/i' => 'almacén de datos',
        ];

        return (string) preg_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
