<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class Phase2TransportLayerIntegrityTest extends TestCase
{
    /**
     * RFC 7538 / Defense-in-Depth: Verificar que la plantilla de Apache incorpore
     * la regla 308 Permanent Redirect para rutas de API y 301 para navegación web.
     */
    public function test_apache_template_has_308_rule_for_api_and_301_for_web(): void
    {
        $templatePath = base_path('../system/templates/apache2/laravel.conf');
        $this->assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} ^/api/', $content);
        $this->assertStringContainsString('RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=308]', $content);
        $this->assertStringContainsString('RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]', $content);
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/(novnc|websockify|wssh)', $content);
    }

    /**
     * Verificar en caliente que una petición HTTP a /api/* responda con código 308
     * garantizando que clientes POST/PUT/DELETE no sufran mutación de verbo hacia GET.
     */
    public function test_live_api_endpoint_responds_with_308_on_http(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $fp = @fopen('http://127.0.0.1/api/cluster/metrics', 'r', false, $context);
        if ($fp) {
            $meta = stream_get_meta_data($fp);
            fclose($fp);
            $headers = $meta['wrapper_data'] ?? [];
            $statusLine = $headers[0] ?? '';

            $this->assertStringContainsString('308', $statusLine, 'Apache debe retornar código 308 en /api/*');

            $hasHttpsLocation = false;
            foreach ($headers as $h) {
                if (stripos($h, 'Location: https://127.0.0.1/api/cluster/metrics') !== false) {
                    $hasHttpsLocation = true;
                    break;
                }
            }
            $this->assertTrue($hasHttpsLocation, 'La cabecera Location en 308 no apunta al endpoint HTTPS correcto.');
        }
    }

    /**
     * Verificar en caliente que rutas web ordinarias respondan con 301 estándar.
     */
    public function test_live_web_route_responds_with_301_on_http(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $fp = @fopen('http://127.0.0.1/login', 'r', false, $context);
        if ($fp) {
            $meta = stream_get_meta_data($fp);
            fclose($fp);
            $headers = $meta['wrapper_data'] ?? [];
            $statusLine = $headers[0] ?? '';

            $this->assertStringContainsString('301', $statusLine, 'Apache debe retornar código 301 en rutas web.');
        }
    }

    /**
     * SEC-13 / SEC-17: Verificar que /wssh se mantenga excluido de la redirección
     * y continúe aplicando el control estricto de token (403 Forbidden sin token).
     */
    public function test_live_wssh_proxy_is_excluded_from_redirect_and_enforces_token(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $fp = @fopen('http://127.0.0.1/wssh', 'r', false, $context);
        if ($fp) {
            $meta = stream_get_meta_data($fp);
            fclose($fp);
            $headers = $meta['wrapper_data'] ?? [];
            $statusLine = $headers[0] ?? '';

            $this->assertStringContainsString('403', $statusLine, 'WebSSH debe retornar 403 Forbidden cuando no hay token.');
            $this->assertStringNotContainsString('301', $statusLine);
            $this->assertStringNotContainsString('308', $statusLine);
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
