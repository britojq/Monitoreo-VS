<?php

declare(strict_types=1);

namespace App\Services\Mcp\Resolvers;

use App\Contracts\ToolIntentMatcherInterface;
use App\Contracts\ToolIntentResolverInterface;
use App\Services\Mcp\Resolvers\Matchers\EmbeddedJsonMatcher;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador desacoplado de resolución de llamadas MCP.
 * Cumple estrictamente con el Principio Abierto/Cerrado (OCP) y Responsabilidad Única (SRP).
 *
 * CERO palabras clave o diccionarios quemados:
 * La intención semántica es responsabilidad exclusiva del motor local de IA mediante
 * el protocolo formal JSON (Tool Calling). Este orquestador solo captura llamadas estructuradas
 * que el modelo emita como JSON embebido en su texto.
 */
class SemanticToolIntentResolver implements ToolIntentResolverInterface
{
    /**
     * @var array<ToolIntentMatcherInterface>
     */
    protected array $matchers;

    /**
     * @param array<ToolIntentMatcherInterface>|null $matchers
     */
    public function __construct(?array $matchers = null)
    {
        $this->matchers = $matchers ?? [
            new EmbeddedJsonMatcher(),
        ];
    }

    /**
     * Evalúa secuencialmente las estrategias de coincidencia registradas (puramente técnicas/sintácticas).
     *
     * @param string $userMessage Mensaje del usuario.
     * @param string $assistantReply Respuesta preliminar en texto generada por la IA.
     * @return array{name: string, arguments: array<string, mixed>}|null
     */
    public function resolveIntent(string $userMessage, string $assistantReply): ?array
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->matches($userMessage, $assistantReply)) {
                $payload = $matcher->resolve($userMessage, $assistantReply);

                Log::info('Llamada a herramienta MCP detectada limpiamente por estructura JSON', [
                    'strategy' => get_class($matcher),
                    'tool' => $payload['name'] ?? 'unknown',
                    'arguments' => $payload['arguments'] ?? [],
                ]);

                return $payload;
            }
        }

        return null;
    }

    /**
     * Permite registrar dinámicamente nuevas estrategias técnicas (Extensión OCP).
     */
    public function registerMatcher(ToolIntentMatcherInterface $matcher, bool $prepend = false): void
    {
        if ($prepend) {
            array_unshift($this->matchers, $matcher);
        } else {
            $this->matchers[] = $matcher;
        }
    }
}
