<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ToolIntentResolverInterface;
use App\Services\Mcp\Resolvers\SemanticToolIntentResolver;
use Tests\TestCase;

class SemanticToolIntentResolverTest extends TestCase
{
    protected ToolIntentResolverInterface $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ToolIntentResolverInterface::class);
    }

    public function test_resolver_is_bound_to_semantic_resolver_in_container(): void
    {
        $this->assertInstanceOf(SemanticToolIntentResolver::class, $this->resolver);
    }

    public function test_embedded_json_matcher_resolves_raw_json_tool_call(): void
    {
        $reply = 'Consultando infraestructura: {"name": "get_ups_telemetry", "arguments": {"ups_id": "LV6KL-VS-01"}}';
        $res = $this->resolver->resolveIntent('dame el estatus del ups', $reply);

        $this->assertNotNull($res);
        $this->assertEquals('get_ups_telemetry', $res['name']);
        $this->assertEquals('LV6KL-VS-01', $res['arguments']['ups_id']);
    }

    public function test_embedded_json_matcher_resolves_inventory_summary_json(): void
    {
        $reply = 'Obteniendo censo: {"name": "get_inventory_summary", "arguments": {"site_name": "Consolidado"}}';
        $res = $this->resolver->resolveIntent('¿cuántos equipos hay?', $reply);

        $this->assertNotNull($res);
        $this->assertEquals('get_inventory_summary', $res['name']);
        $this->assertEquals('Consolidado', $res['arguments']['site_name']);
    }

    public function test_resolver_has_zero_keyword_dictionaries_and_delegates_to_ai(): void
    {
        // Al no existir diccionarios de palabras clave, el texto plano no genera herramientas artificiales;
        // el resolver retorna null para permitir que la respuesta natural del modelo fluya limpiamente.
        $reply = 'Soy Monitor Valle Seco, el asistente virtual corporativo. ¿En qué puedo asistirte?';
        $res = $this->resolver->resolveIntent('cuantos equipos hay en la sede', $reply);

        $this->assertNull($res);
    }

    public function test_resolver_returns_null_when_no_tool_call_is_present(): void
    {
        $res = $this->resolver->resolveIntent('Hola, ¿cómo estás?', 'Hola, todo en orden en el centro de operaciones.');
        $this->assertNull($res);
    }
}
