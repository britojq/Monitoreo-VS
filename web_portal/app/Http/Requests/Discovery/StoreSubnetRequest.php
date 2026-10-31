<?php

declare(strict_types=1);

namespace App\Http\Requests\Discovery;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubnetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'subnet' => ['required', 'string', 'unique:discovery_subnets,subnet', 'regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/'],
            'site_id' => ['nullable', 'exists:monitored_sites,id'],
            'scan_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'rate_limit_pps' => ['required', 'integer', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'subnet.regex' => 'La subred debe tener formato CIDR válido (ej: 192.168.1.0/24).',
            'subnet.unique' => 'Esta subred ya está registrada.',
        ];
    }
}
