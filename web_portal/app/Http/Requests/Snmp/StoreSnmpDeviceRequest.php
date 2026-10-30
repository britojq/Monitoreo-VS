<?php

declare(strict_types=1);

namespace App\Http\Requests\Snmp;

use Illuminate\Foundation\Http\FormRequest;

class StoreSnmpDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:snmp_devices,ip_address',
            'snmp_version' => 'required|in:v2c,v3',
            'community' => 'nullable|string|max:255',
            'device_type' => 'required|in:router,switch,firewall,server,ups,ap,unknown',
            'site_id' => 'nullable|exists:monitored_sites,id',
            'poll_interval_seconds' => 'nullable|integer|min:10|max:3600',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
