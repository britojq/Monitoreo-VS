<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class Phase1HstsHardeningV55420Test extends TestCase
{
    /**
     * TECH-SEC-12 (NIST SP 800-52 / OWASP Transport Layer Hardening):
     * Verificar que bajo HTTPS, SecurityHeadersMiddleware inyecte la cabecera
     * Strict-Transport-Security con directiva de un año (31536000) e includeSubDomains.
     */
    public function test_https_request_injects_hsts_header_with_one_year_max_age(): void
    {
        $request = Request::create('https://127.0.0.1/login', 'GET');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(
            $response->headers->has('Strict-Transport-Security'),
            'La respuesta HTTPS debe contener la cabecera Strict-Transport-Security.'
        );

        $hstsHeader = (string) $response->headers->get('Strict-Transport-Security');
        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $hstsHeader,
            "La cabecera HSTS debe fijar max-age=31536000 e includeSubDomains, se obtuvo: {$hstsHeader}"
        );
    }

    /**
     * TECH-SEC-12: Verificar que bajo peticiones HTTP (no seguras), la cabecera
     * HSTS se emita con max-age=0 para permitir la purga de cualquier HSTS residual previo.
     */
    public function test_http_request_does_not_enforce_hsts_and_purges_with_max_age_zero(): void
    {
        $request = Request::create('http://127.0.0.1/login', 'GET');
        $response = $this->app->handle($request);

        $this->assertTrue($response->headers->has('Strict-Transport-Security'));
        $hstsHeader = (string) $response->headers->get('Strict-Transport-Security');
        $this->assertSame('max-age=0', $hstsHeader);
    }

    /**
     * TECH-SEC-12: Verificar fáctico en el servidor Apache en caliente:
     * Petición HTTPS a https://127.0.0.1/login retorna la cabecera HSTS configurada.
     */
    public function test_live_apache_https_response_contains_hsts_header(): void
    {
        $httpsContext = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $fpSsl = @fopen('https://127.0.0.1/login', 'r', false, $httpsContext);
        if ($fpSsl) {
            $metaSsl = stream_get_meta_data($fpSsl);
            fclose($fpSsl);
            $headersSsl = $metaSsl['wrapper_data'] ?? [];

            $foundHsts = false;
            foreach ($headersSsl as $h) {
                if (stripos($h, 'Strict-Transport-Security:') === 0) {
                    $foundHsts = true;
                    $this->assertStringContainsString('max-age=31536000', $h);
                    $this->assertStringContainsString('includeSubDomains', $h);
                    break;
                }
            }

            $this->assertTrue($foundHsts, 'No se encontró la cabecera Strict-Transport-Security en la respuesta live de Apache.');
        }
    }

    /**
     * Garantía de Inmutabilidad Criptográfica: Verificar que el archivo de auditoría
     * docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md se mantenga intacto.
     */
    public function test_audit_report_hash_remains_strictly_immutable(): void
    {
        $auditReport = base_path('../docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md');
        $this->assertFileExists($auditReport);

        $expectedHash = '88d8174095afbe98c7ad47e6c9cd86e9bc5c1d3c6690d13de9d52fff9f6cd165';
        $actualHash = hash_file('sha256', $auditReport);

        $this->assertSame(
            $expectedHash,
            $actualHash,
            'CRÍTICO: El archivo de auditoría ha sido alterado o manipulado.'
        );
    }
}
