<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Glpi\GlpiPayloadDecoder;
use Tests\TestCase;

class Phase3SecurityHardeningTest extends TestCase
{
    /**
     * SEC-10 (CWE-611): Verificar que GlpiPayloadDecoder previene ataques XXE / SSRF mediante LIBXML_NONET.
     */
    public function test_glpi_payload_decoder_blocks_xxe_external_entities(): void
    {
        $decoder = new GlpiPayloadDecoder();

        // XML con entidad externa simulando intento de SSRF
        $xxePayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE test [
  <!ENTITY xxe SYSTEM "http://127.0.0.1:9999/malicious-entity">
]>
<REQUEST>
  <DEVICEID>TEST-HOST</DEVICEID>
  <QUERY>PROLOG</QUERY>
  <DATA>&xxe;</DATA>
</REQUEST>
XML;

        $result = $decoder->decode($xxePayload);

        $this->assertSame('xml', $result['format']);
        $this->assertIsArray($result['parsed']);
        // La entidad externa no debe ser resuelta debido a LIBXML_NONET
        $this->assertNotSame('http://127.0.0.1:9999/malicious-entity', $result['parsed']['DATA'] ?? null);
    }

    /**
     * SEC-11 (CWE-200): Verificar que peticiones GET al receptor GLPI respondan HTTP 204 No Content sin divulgación de información.
     */
    public function test_glpi_receiver_get_endpoint_returns_204_without_disclosure(): void
    {
        $response = $this->get('/api/v1/inventory/receiver');

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
        $this->assertStringNotContainsString('version', (string) $response->getContent());
        $this->assertStringNotContainsString('Valle Seco', (string) $response->getContent());
    }

    /**
     * SEC-12 (CWE-601 / CWE-494): Verificar que /deploy/linux rechace URLs no autorizadas con HTTP 403.
     */
    public function test_deploy_linux_rejects_untrusted_deploy_base(): void
    {
        // Host no autorizado fuera del clúster
        $response = $this->get('/deploy/linux?deploy_base=http://evil-attacker.site/binaries');
        $response->assertStatus(403);

        // Host de servidor no autorizado
        $responseServer = $this->get('/deploy/linux?server_url=http://malicious-node.xyz/api');
        $responseServer->assertStatus(403);

        // Host legítimo del clúster (192.168.1.220)
        $responseValid = $this->get('/deploy/linux?deploy_base=http://192.168.1.220/instaladores');
        $responseValid->assertStatus(200);
        $this->assertStringContainsString('glpi-agent', $responseValid->getContent());
    }

    /**
     * SEC-12 (CWE-601 / CWE-494): Verificar que /deploy/win rechace URLs no autorizadas con HTTP 403.
     */
    public function test_deploy_win_rejects_untrusted_deploy_base(): void
    {
        // Host no autorizado fuera del clúster
        $response = $this->get('/deploy/win?deploy_base=http://evil-attacker.site/binaries');
        $response->assertStatus(403);

        // Host legítimo del clúster (192.168.1.220)
        $responseValid = $this->get('/deploy/win?deploy_base=http://192.168.1.220/instaladores');
        $responseValid->assertStatus(200);
    }
}
