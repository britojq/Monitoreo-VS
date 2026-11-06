<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Contracts\CorporateAiInferenceServiceInterface;
use App\Contracts\OllamaServiceInterface;
use App\Contracts\ToolExecutorInterface;
use App\Contracts\ToolIntentResolverInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Servicio Orquestador de Inferencia y Tool Calling para el Asistente Corporativo.
 * Principio de Responsabilidad Única (SRP) y Neutralidad Tecnológica (Regla de Oro #2).
 */
class CorporateAiInferenceService implements CorporateAiInferenceServiceInterface, OllamaServiceInterface
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

        $messages = [];
        $messages[] = [
            'role' => 'system',
            'content' => $this->getSystemPrompt($userName, $userRole),
        ];

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

        $sanitizedUserMessage = $this->sanitizeInput($cleanUserMessage);
        $messages[] = [
            'role' => 'user',
            'content' => $sanitizedUserMessage,
        ];

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
                Log::error('MCP AI Inference HTTP Error (Llamada inicial)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'reply' => '',
                    'tool_executed' => null,
                    'tool_result' => null,
                    'error' => 'El servicio corporativo de IA reportó un error al procesar la solicitud.',
                ];
            }

            $responseMessage = $response->json('message', []);
            $toolCalls = $responseMessage['tool_calls'] ?? [];

            if (!empty($toolCalls)) {
                return $this->handleToolCalls($toolCalls, $messages, $responseMessage);
            }

            $directReply = trim((string) ($responseMessage['content'] ?? ''));
            $fallbackTool = (!$isGroup && $this->intentResolver)
                ? $this->intentResolver->resolveIntent($sanitizedUserMessage, $directReply)
                : null;

            if ($fallbackTool !== null) {
                return $this->handleFallbackTool($fallbackTool, $messages);
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
            Log::error('MCP AI Inference Connection Exception', ['error' => $e->getMessage()]);
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
            Log::error('MCP AI Inference Unexpected Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'reply' => '',
                'tool_executed' => null,
                'tool_result' => null,
                'error' => 'Ocurrió un error inesperado al procesar la consulta en el asistente corporativo.',
            ];
        }
    }

    protected function handleToolCalls(array $toolCalls, array $messages, array $responseMessage): array
    {
        $lastToolName = null;
        $lastToolResult = null;
        $messages[] = $responseMessage;

        foreach ($toolCalls as $call) {
            $func = $call['function'] ?? [];
            $toolName = (string) ($func['name'] ?? '');
            $toolArgs = (array) ($func['arguments'] ?? []);

            $toolResult = $this->toolExecutor->executeTool($toolName, $toolArgs);
            $lastToolName = $toolName;
            $lastToolResult = $toolResult;

            $messages[] = [
                'role' => 'tool',
                'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
            ];
        }

        $secondResponse = Http::timeout($this->timeout)->post($this->url, [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'keep_alive' => '24h',
            'options' => ['temperature' => 0.1, 'num_ctx' => 4096],
        ]);

        if ($secondResponse->successful()) {
            $finalReply = trim((string) $secondResponse->json('message.content', ''));
            return [
                'success' => true,
                'reply' => $this->sanitizeOutput($finalReply ?: 'No se obtuvo una respuesta válida del motor de IA tras procesar la herramienta.'),
                'tool_executed' => $lastToolName,
                'tool_result' => $lastToolResult,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'reply' => '',
            'tool_executed' => $lastToolName,
            'tool_result' => $lastToolResult,
            'error' => 'Error al sintetizar los datos de la herramienta con el motor local de IA.',
        ];
    }

    protected function handleFallbackTool(array $fallbackTool, array $messages): array
    {
        $toolName = $fallbackTool['name'];
        $toolArgs = $fallbackTool['arguments'];
        $toolResult = $this->toolExecutor->executeTool($toolName, $toolArgs);

        $messages[] = ['role' => 'assistant', 'content' => "Consultando datos de infraestructura ({$toolName})..."];
        $messages[] = ['role' => 'tool', 'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)];

        $secondResponse = Http::timeout($this->timeout)->post($this->url, [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'keep_alive' => '24h',
            'options' => ['temperature' => 0.1, 'num_ctx' => 4096],
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

        return [
            'success' => false,
            'reply' => '',
            'tool_executed' => $toolName,
            'tool_result' => $toolResult,
            'error' => 'Fallo al procesar herramienta de respaldo.',
        ];
    }

    public function getSystemPrompt(?string $userName = null, ?string $userRole = null): string
    {
        return CorporateAiPromptCatalog::getPrompt($userName, $userRole);
    }

    public function getToolSchema(): array
    {
        return CorporateAiToolCatalog::getSchemas();
    }

    public function sanitizeInput(string $input): string
    {
        return trim(strip_tags($input));
    }

    public function sanitizeOutput(string $text): string
    {
        $replacements = [
            '/\b(ollama|ollama-server|ollama_model)\b/i' => 'motor local de IA',
            '/\b(mariadb|mysql|innodb)\b/i' => 'base de datos corporativa',
            '/\b(sqlite|postgresql|postgres)\b/i' => 'almacén de datos',
        ];

        return (string) preg_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
