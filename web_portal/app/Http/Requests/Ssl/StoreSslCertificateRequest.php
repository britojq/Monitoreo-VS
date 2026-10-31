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
            'domain' => 'required|string|max:255|unique:ssl_certificates,domain',
            'port' => 'nullable|integer|min:1|max:65535',
            'service_id' => 'nullable|exists:monitored_services,id',
            'alert_threshold_warning' => 'nullable|integer|min:1|max:365',
            'alert_threshold_critical' => 'nullable|integer|min:1|max:365',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
