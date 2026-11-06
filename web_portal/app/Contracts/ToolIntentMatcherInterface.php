<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato para estrategias individuales de coincidencia y resolución de intención (Patrón Strategy).
 * Principio Abierto/Cerrado (OCP): Permite agregar soporte para nuevas intenciones o herramientas
 * mediante nuevas clases sin modificar el orquestador principal.
 */
interface ToolIntentMatcherInterface
{
    /**
     * Determina si este matcher aplica para la consulta del usuario o respuesta del asistente.
     */
    public function matches(string $userMessage, string $assistantReply): bool;

    /**
     * Resuelve y construye el payload con el nombre de la herramienta y sus argumentos validados.
     *
     * @return array{name: string, arguments: array<string, mixed>}
     */
    public function resolve(string $userMessage, string $assistantReply): array;
}
