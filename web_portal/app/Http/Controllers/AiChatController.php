<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Ai\ProcessChatPromptAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    /**
     * Procesar consulta del usuario y responder usando el motor local de IA.
     */
    public function chat(Request $request, ProcessChatPromptAction $action): JsonResponse
    {
        $result = $action->execute($request);
        $statusCode = $result['status_code'] ?? 200;
        unset($result['status_code']);

        return response()->json($result, $statusCode);
    }

    /**
     * Reiniciar historial y memoria anafórica de la sesión web.
     */
    public function reset(): JsonResponse
    {
        session()->forget(['ai_chat_history', 'ai_active_entity']);

        return response()->json([
            'success' => true,
            'message' => 'Historial y memoria conversacional reiniciados con éxito.',
        ]);
    }
}
