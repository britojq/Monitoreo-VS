<?php

declare(strict_types=1);

namespace App\Http\Requests\Ssl;

use Illuminate\Foundation\Http\FormRequest;

class StoreSslCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            // F-SEC-01: Validación estricta de FQDN / IPv4 sin metacaracteres shell (RFC 1035 / OWASP A03)
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^(([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z0-9-]{2,63}|((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))$/',
                'unique:ssl_certificates,domain',
            ],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'service_id' => ['nullable', 'exists:monitored_services,id'],
            'alert_threshold_warning' => ['nullable', 'integer', 'min:1', 'max:365'],
            'alert_threshold_critical' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.regex' => 'El dominio debe ser un FQDN o dirección IPv4 válida (ej. subdominio.empresa.com o 192.168.1.1). No se permiten caracteres especiales ni espacios.',
        ];
    }
}
