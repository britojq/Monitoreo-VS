<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato de Inversión de Dependencias (DIP) para el Motor de Inferencia y Asistente Corporativo.
 * En estricto cumplimiento de la Regla de Oro #2 (Tecnología Neutral y Cero Menciones a Motores Subyacentes).
 */
interface CorporateAiInferenceServiceInterface
{
    /**
     * Orquesta el bucle conversacional completo con soporte de invocación de herramientas (Tool Calling).
     *
     * @param string $userMessage Mensaje enviado por el usuario.
     * @param array<int, array<string, string>> $history Historial previo de la conversación.
     * @param string|null $userName Nombre del usuario autenticado.
     * @param string|null $userRole Rol del usuario en el sistema.
     * @return array<string, mixed> Resultado estructurado con la respuesta y metadatos de tools.
     */
    public function chat(
        string $userMessage,
        array $history = [],
        ?string $userName = null,
        ?string $userRole = null
    ): array;

    /**
     * Retorna el System Prompt institucional con blindaje de neutralidad técnica.
     */
    public function getSystemPrompt(?string $userName = null, ?string $userRole = null): string;

    /**
     * Retorna el esquema JSON de herramientas compatibles con el motor de inferencia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolSchema(): array;
}
