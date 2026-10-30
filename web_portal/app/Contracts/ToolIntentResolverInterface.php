<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato formal para la resolución desacoplada de intenciones de herramientas MCP.
 * Principio de Inversión de Dependencias (DIP) y Responsabilidad Única (SRP).
 */
interface ToolIntentResolverInterface
{
    /**
     * Evalúa el mensaje del usuario y la respuesta emitida por el modelo para determinar
     * si se requiere invocar una herramienta del catálogo MCP como contingencia (fallback).
     *
     * @param string $userMessage Mensaje del usuario sanitizado.
     * @param string $assistantReply Respuesta preliminar en texto generada por el motor de IA.
     * @return array{name: string, arguments: array<string, mixed>}|null Retorna el payload de la herramienta o null si no aplica.
     */
    public function resolveIntent(string $userMessage, string $assistantReply): ?array;
}
