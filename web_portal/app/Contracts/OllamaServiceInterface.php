<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato para el Servicio Orquestador de Tool Calling con el motor local de IA.
 * Principio de Inversión de Dependencias (DIP) y Responsabilidad Única (SRP).
 */
interface OllamaServiceInterface
{
    /**
     * Orquesta el bucle conversacional completo con soporte nativo de Tool Calling.
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
     * Retorna el System Prompt institucional con blindaje de la Regla de Oro #2.
     *
     * @param string|null $userName
     * @param string|null $userRole
     * @return string
     */
    public function getSystemPrompt(?string $userName = null, ?string $userRole = null): string;

    /**
     * Retorna el esquema JSON de herramientas compatibles con Ollama /api/chat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolSchema(): array;
}
