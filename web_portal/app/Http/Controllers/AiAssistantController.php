<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\OllamaServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador Orquestador del Asistente Virtual MCP con Tool Calling para Clúster/Telegram.
 * Principio de Responsabilidad Única (SRP) y Controlador Delgado.
 */
class AiAssistantController extends Controller
{
    public function __construct(
        protected OllamaServiceInterface $ollamaService
    ) {}

    /**
     * Procesa la consulta para clientes API del clúster (Bot de Telegram / Servicios internos).
     * Requiere autenticación mediante ValidateClusterToken (cabecera X-Cluster-Token).
     */
    public function apiChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant,tool,system'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
            'user_name' => ['nullable', 'string', 'max:100'],
            'user_role' => ['nullable', 'string', 'max:50'],
        ]);

        @set_time_limit(240);

        $result = $this->ollamaService->chat(
            userMessage: trim($validated['message']),
            history: $validated['history'] ?? [],
            userName: $validated['user_name'] ?? 'Administrador Telegram',
            userRole: $validated['user_role'] ?? 'Administrador'
        );

        $statusCode = $result['success'] ? 200 : 502;

        return response()->json([
            'success' => $result['success'],
            'reply' => $result['reply'],
            'tool_executed' => $result['tool_executed'] ?? null,
            'error' => $result['error'] ?? null,
        ], $statusCode);
    }
}
