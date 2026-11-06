<?php

declare(strict_types=1);

namespace App\Services\Mcp\Resolvers\Matchers;

use App\Contracts\ToolIntentMatcherInterface;

/**
 * Estrategia de detección de llamadas a herramientas codificadas como JSON crudo dentro de la respuesta del modelo.
 */
class EmbeddedJsonMatcher implements ToolIntentMatcherInterface
{
    protected const ALLOWED_TOOLS = [
        'get_inventory_summary',
        'get_ups_telemetry',
        'search_itam_asset',
        'get_noc_site_status',
        'get_proxy_status',
    ];

    public function matches(string $userMessage, string $assistantReply): bool
    {
        return (bool) preg_match('/\{\s*"name"\s*:\s*"([a-z_]+)"\s*,\s*"arguments"\s*:\s*(\{[^}]*\})\s*\}/i', $assistantReply);
    }

    public function resolve(string $userMessage, string $assistantReply): array
    {
        if (preg_match('/\{\s*"name"\s*:\s*"([a-z_]+)"\s*,\s*"arguments"\s*:\s*(\{[^}]*\})\s*\}/i', $assistantReply, $matches)) {
            $toolName = strtolower(trim($matches[1]));
            $args = json_decode($matches[2], true) ?: [];

            if (in_array($toolName, self::ALLOWED_TOOLS, true)) {
                return [
                    'name' => $toolName,
                    'arguments' => $args,
                ];
            }
        }

        return [
            'name' => 'get_inventory_summary',
            'arguments' => ['site_name' => 'Consolidado'],
        ];
    }
}
