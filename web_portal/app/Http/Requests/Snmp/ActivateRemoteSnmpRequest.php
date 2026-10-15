<?php

declare(strict_types=1);

namespace App\Http\Requests\Snmp;

use Illuminate\Foundation\Http\FormRequest;

class ActivateRemoteSnmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ip_address' => 'required|ip',
            'method' => 'required|in:ssh,telnet,pfsense',
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:100',
            'secret' => 'nullable|string|max:100',
            'community' => 'required|string|max:100',
            'port' => 'nullable|integer|min:1|max:65535',
        ];
    }
}
