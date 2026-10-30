<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkPromoteAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission('inventory.deploy');
    }

    public function rules(): array
    {
        return [
            'devices' => ['required', 'array', 'min:1'],
            'devices.*.ip' => ['required', 'ip'],
            'devices.*.mac' => ['nullable', 'string', 'max:48'],
            'devices.*.hostname' => ['nullable', 'string', 'max:128'],
            'devices.*.vendor' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:desktop,laptop,server,printer,router,switch,firewall,access_point,ip_phone,ups'],
            'monitored_site_id' => ['required', 'exists:monitored_sites,id'],
            'name_prefix' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
