<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PromoteAssetRequest extends FormRequest
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
            'ip' => ['required', 'ip'],
            'mac' => ['nullable', 'string', 'max:48'],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', 'string', 'in:desktop,laptop,server,printer,router,switch,firewall,access_point,ip_phone,ups'],
            'monitored_site_id' => ['required', 'exists:monitored_sites,id'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:64'],
            'serial' => ['nullable', 'string', 'max:64'],
            'ports' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
