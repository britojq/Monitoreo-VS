<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SystemKnowledgeGraphInterface;
use Tests\TestCase;

class SystemKnowledgeGraphTest extends TestCase
{
    public function test_knowledge_graph_builds_and_resolves_ups(): void
    {
        $service = app(SystemKnowledgeGraphInterface::class);
        $graph = $service->buildGraph(true);

        $this->assertGreaterThan(50, $graph['node_count']);
        $this->assertGreaterThan(50, $graph['edge_count']);

        $res = $service->resolveQueryContext('cuál es el serial del UPS');
        $this->assertStringContainsString('UPS-DEMO-01', $res['context']);
        $this->assertStringContainsString('UPS ZTG LV6KL', $res['context']);
        $this->assertStringStartsWith('ups_', (string) $res['active_node_id']);
    }

    public function test_knowledge_graph_resolves_ups_power_events(): void
    {
        $service = app(SystemKnowledgeGraphInterface::class);
        $res = $service->resolveQueryContext('Cuántas averías ha detectado el histórico del UPS');

        $this->assertStringContainsString('HISTORIAL DE AVERÍAS Y EVENTOS ELÉCTRICOS', $res['context']);
        $this->assertStringContainsString('incidencias registradas en MariaDB (SSOT)', $res['context']);
        $this->assertStringContainsString('UPS-DEMO-01', $res['context']);
    }

    public function test_knowledge_graph_disambiguation(): void
    {
        $service = app(SystemKnowledgeGraphInterface::class);

        // Paso 1: Consultar SW03
        $resSw = $service->resolveQueryContext('Háblame de SW03');
        $this->assertStringContainsString('SW03', $resSw['context']);
        $this->assertStringStartsWith('device_', (string) $resSw['active_node_id']);

        // Paso 2: Consultar UPS arrastrando el nodo anterior
        $resUps = $service->resolveQueryContext('cuál es el serial del UPS', $resSw['active_node_id']);
        $this->assertStringContainsString('UPS-DEMO-01', $resUps['context']);
        $this->assertStringStartsWith('ups_', (string) $resUps['active_node_id']);
    }

    public function test_ai_knowledge_engine_service_uses_graph(): void
    {
        $engine = app(\App\Services\Ai\AiKnowledgeEngineService::class);

        // Consultar averías del UPS a través del motor completo
        $res = $engine->resolveSystemContext('Cuántas averías ha detectado el histórico del UPS');
        $this->assertNotNull($res['context']);
        $this->assertStringContainsString('UPS-DEMO-01', $res['context']);
        $this->assertStringContainsString('HISTORIAL DE AVERÍAS Y EVENTOS ELÉCTRICOS', $res['context']);
        $this->assertSame('ups_1', $res['active_entity']['node_id'] ?? null);
    }

    public function test_knowledge_graph_all_domains_coverage(): void
    {
        $engine = app(\App\Services\Ai\AiKnowledgeEngineService::class);

        // 1. Tickets de Mantenimiento ITAM
        $resT = $engine->resolveSystemContext('cuántos tickets de mantenimiento hay abiertos');
        $this->assertNotNull($resT['context']);
        $this->assertStringContainsString('DOMINIO DE TICKETS Y MANTENIMIENTO TÉCNICO ITAM', $resT['context']);

        // 2. Alertas NOC
        $resA = $engine->resolveSystemContext('cuáles son las alertas activas del sistema');
        $this->assertNotNull($resA['context']);
        $this->assertStringContainsString('DOMINIO DE ALERTAS E INCIDENTES NOC', $resA['context']);

        // 3. Subredes e IPAM
        $resS = $engine->resolveSystemContext('qué subredes y bloques IP están configurados');
        $this->assertNotNull($resS['context']);
        $this->assertStringContainsString('DOMINIO DE SUBREDES Y PLAN IP', $resS['context']);
        $this->assertStringContainsString('192.168.1.0/24', $resS['context']);

        // 4. Certificados SSL
        $resC = $engine->resolveSystemContext('cuándo vence el certificado SSL o TLS');
        $this->assertNotNull($resC['context']);
        $this->assertStringContainsString('DOMINIO DE CERTIFICADOS SSL', $resC['context']);
        $this->assertStringContainsString('core.telegram.org', $resC['context']);

        // 5. Servicios Corporativos y Estado NOC
        $resServ = $engine->resolveSystemContext('cuantos servicios estan caidos');
        $this->assertNotNull($resServ['context']);
        $this->assertStringContainsString('DOMINIO DE SERVICIOS CORPORATIVOS Y ESTADO NOC', $resServ['context']);
        $this->assertStringContainsString('ESTADO_OPERATIVO_BASE_NOC', $resServ['context']);
        $this->assertStringContainsString('OTRS', $resServ['context']);
        $this->assertStringContainsString('INTRANET empresa', $resServ['context']);
    }

    public function test_output_guardrail_sanitizes_refusals_and_contradictions(): void
    {
        $controller = app(\App\Http\Controllers\AiChatController::class);
        $method = new \ReflectionMethod($controller, 'validateAndSanitizeResponse');
        $method->setAccessible(true);

        // Caso 1: Falsa declinación sobre segmentos
        $canned = 'Lo siento, pero no tengo acceso a la información específica sobre los segmentos de red...';
        $fixed = $method->invoke($controller, $canned, 'Dame los segmentos de red de las sedes en el sistema', null);
        $this->assertStringContainsString('192.168.107.0/24', $fixed);
        $this->assertStringContainsString('192.168.106.0/24', $fixed);
        $this->assertStringContainsString('192.168.1.0/24', $fixed);

        // Caso 2: Contradicción fáctica sobre servicios caídos
        $hallucination = 'Actualmente no hay ningún servicio caído registrado en el sistema. Todos están en línea.';
        $fixedOutage = $method->invoke($controller, $hallucination, 'cuantos servicios estan caidos', null);
        $this->assertStringContainsString('servicios caídos', $fixedOutage);
        $this->assertStringContainsString('OTRS', $fixedOutage);
        $this->assertStringContainsString('INTRANET empresa', $fixedOutage);
    }

    public function test_knowledge_graph_person_subgraph_resolution(): void
    {
        $service = app(SystemKnowledgeGraphInterface::class);
        $res = $service->resolveQueryContext('Qué equipo tiene Anais?');

        $this->assertSame('person_anais_c_colmenarez_g', $res['active_node_id']);
        $this->assertStringContainsString('ANAIS C. COLMENAREZ G.', $res['context']);
        $this->assertStringContainsString('EQUIPO 01', $res['context']);
        $this->assertStringContainsString('MXL52434KR', $res['context']);
        $this->assertStringContainsString('V1PAF51', $res['context']);
        $this->assertStringContainsString('assignments/scans/UINF-4.05-2026-0014_ACTA_EQUIPO_01_TRANSMISION.pdf', $res['context']);
    }

    public function test_knowledge_graph_scanned_actas_resolution(): void
    {
        $service = app(SystemKnowledgeGraphInterface::class);
        $res = $service->resolveQueryContext('dame un listado de los equipos que tienen acta de asignacion');

        $this->assertStringContainsString('UINF-4.05-2026-0014', $res['context']);
        $this->assertStringContainsString('UINF-4.05-2026-0015', $res['context']);
        $this->assertStringContainsString('UINF-4.05-2026-0016', $res['context']);
        $this->assertStringContainsString('ANAIS C. COLMENAREZ G.', $res['context']);
        $this->assertStringContainsString('SORANGEL A. ORTIZ S.', $res['context']);
        $this->assertStringContainsString('TANIA ALVIS', $res['context']);
    }

    public function test_knowledge_graph_peripheral_matching(): void
    {
        $service = app(SystemKnowledgeGraphInterface::class);
        $res = $service->resolveQueryContext('Cuántos monitores HP tenemos registrados?');

        $this->assertStringContainsString('DOMINIO DE PERIFÉRICOS DE HARDWARE ITAM', $res['context']);
        $this->assertStringContainsString('Periféricos Coincidentes con la Consulta: 8 unidades', $res['context']);
        $this->assertStringContainsString('HP V193b', $res['context']);
    }

    public function test_knowledge_graph_cencaratit_and_router(): void
    {
        $service = app(SystemKnowledgeGraphInterface::class);

        // CENCARATIT
        $resC = $service->resolveQueryContext('Qué componentes tiene el equipo CENCARATIT?');
        $this->assertSame('device_11519', $resC['active_node_id']);
        $this->assertStringContainsString('CENCARATIT (Servidor Desarrollo)', $resC['context']);
        $this->assertStringContainsString('192.168.1.220', $resC['context']);

        // Router Principal
        $resR = $service->resolveQueryContext('A qué switch y puerto está conectado el Router Principal?');
        $this->assertSame('device_1', $resR['active_node_id']);
        $this->assertStringContainsString('ROUTER PRINCIPAL', $resR['context']);
        $this->assertStringContainsString('192.168.1.1', $resR['context']);
    }
}
