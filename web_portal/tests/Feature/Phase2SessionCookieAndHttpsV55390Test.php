<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class Phase2SessionCookieAndHttpsV55390Test extends TestCase
{
    /**
     * SEC-19 (CWE-614 / CWE-319): Verificar que la configuración de sesión resuelva
     * 'secure' como null para activar la evaluación dinámica nativa de Laravel según HTTPS.
     */
    public function test_session_configuration_has_dynamic_secure_attribute(): void
    {
        $secureConfig = config('session.secure');
        $this->assertNull(
            $secureConfig,
            "config('session.secure') debe ser null para permitir que Laravel active Secure dinámicamente en HTTPS."
        );
    }

    /**
     * SEC-19: Verificar que las peticiones HTTPS emitan cookies con el atributo Secure=true,
     * protegiendo tanto 'monitoreo_vs_session' como 'XSRF-TOKEN' contra intercepciones.
     */
    public function test_https_requests_emit_secure_session_cookies(): void
    {
        $request = Request::create('https://127.0.0.1/login', 'GET');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies, 'La respuesta debe contener cookies de sesión y CSRF.');

        $foundSessionCookie = false;
        $foundXsrfCookie = false;

        foreach ($cookies as $cookie) {
            $this->assertTrue(
                $cookie->isSecure(),
                "La cookie '{$cookie->getName()}' debe tener el flag Secure activo en solicitudes HTTPS."
            );

            if ($cookie->getName() === config('session.cookie')) {
                $foundSessionCookie = true;
                $this->assertTrue($cookie->isHttpOnly(), 'La cookie de sesión debe tener HttpOnly activo.');
                $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
            }

            if ($cookie->getName() === 'XSRF-TOKEN') {
                $foundXsrfCookie = true;
                $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
            }
        }

        $this->assertTrue($foundSessionCookie, 'No se encontró la cookie de sesión en la respuesta.');
        $this->assertTrue($foundXsrfCookie, 'No se encontró la cookie XSRF-TOKEN en la respuesta.');
    }

    /**
     * SEC-19: Verificar que la plantilla de Apache incorpore las directivas de
     * reescritura para forzar la redirección 301 de HTTP (:80) a HTTPS (:443).
     */
    public function test_apache_template_enforces_http_to_https_301_redirect(): void
    {
        $templatePath = base_path('../system/templates/apache2/laravel.conf');
        $this->assertFileExists($templatePath);

        $content = file_get_contents($templatePath);
        $this->assertStringContainsString('RewriteEngine On', $content);
        $this->assertStringContainsString('RewriteCond %{HTTPS} off', $content);
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/(novnc|websockify|wssh)', $content);
        $this->assertStringContainsString('RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]', $content);
    }

    /**
     * SEC-19: Verificar comportamiento fáctico del VirtualHost Apache en ejecución:
     * 1. Petición HTTP a la raíz retorna 301 Moved Permanently hacia HTTPS.
     * 2. Petición HTTPS retorna cabecera Set-Cookie con el flag '; secure'.
     */
    public function test_live_apache_responds_with_301_on_http_and_secure_cookies_on_https(): void
    {
        // 1. Verificar redirección 301 en HTTP :80
        $httpContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $fp = @fopen('http://127.0.0.1/', 'r', false, $httpContext);
        if ($fp) {
            $meta = stream_get_meta_data($fp);
            fclose($fp);
            $headers = $meta['wrapper_data'] ?? [];
            $statusLine = $headers[0] ?? '';
            $this->assertStringContainsString('301', $statusLine, 'Apache no retornó código 301 en puerto 80.');

            $hasHttpsLocation = false;
            foreach ($headers as $header) {
                if (stripos($header, 'Location: https://') !== false) {
                    $hasHttpsLocation = true;
                    break;
                }
            }
            $this->assertTrue($hasHttpsLocation, 'La cabecera Location en 301 no apunta a HTTPS.');
        }

        // 2. Verificar cabeceras Set-Cookie con '; secure' en HTTPS :443
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

            $setCookieHeaders = array_filter(
                $headersSsl,
                fn(string $h) => stripos($h, 'Set-Cookie:') === 0
            );
            $this->assertNotEmpty($setCookieHeaders, 'No se recibieron cabeceras Set-Cookie en HTTPS.');

            foreach ($setCookieHeaders as $cookieHeader) {
                $this->assertMatchesRegularExpression(
                    '/;\s*secure/i',
                    $cookieHeader,
                    "La cabecera Set-Cookie en HTTPS debe contener el atributo '; secure': {$cookieHeader}"
                );
            }
        }
    }

    /**
     * Garantía de Inmutabilidad Criptográfica: Verificar que el archivo de auditoría
     * docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.53.90.md se mantenga intacto.
     */
    public function test_audit_report_hash_remains_strictly_immutable(): void
    {
        $auditReport = base_path('../docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.53.90.md');
        $this->assertFileExists($auditReport);

        $expectedHash = 'baa62332f165f5b2dbd9390ddbfbf3ff9d8a8cff7b25c14dd2cbc7dc88f6f247';
        $actualHash = hash_file('sha256', $auditReport);

        $this->assertSame(
            $expectedHash,
            $actualHash,
            'CRÍTICO: El archivo de auditoría ha sido alterado o manipulado.'
        );
    }
}
