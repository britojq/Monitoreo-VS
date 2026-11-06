<?php

declare(strict_types=1);

namespace App\Http\Requests\Snmp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSnmpDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:snmp_devices,ip_address,' . $id,
            'snmp_port' => 'nullable|integer|min:1|max:65535',
            'snmp_version' => 'required|in:v2c,v3',
            'community' => 'nullable|string|max:255',
            'device_type' => 'required|in:router,switch,firewall,server,ups,ap,unknown',
            'site_id' => 'nullable|exists:monitored_sites,id',
            'poll_interval_seconds' => 'nullable|integer|min:10|max:3600',
            'is_active' => 'nullable',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
