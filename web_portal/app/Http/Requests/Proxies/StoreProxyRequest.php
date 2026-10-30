<?php

declare(strict_types=1);

namespace App\Http\Requests\Proxies;

use App\DTOs\ProxyData;
use Illuminate\Foundation\Http\FormRequest;

class StoreProxyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'letter' => ['required', 'string', 'max:5', 'unique:monitored_proxies'],
            'name' => ['required', 'string', 'max:255'],
            'ip_port' => ['required', 'string', 'max:255'],
            'auth_userpass' => ['nullable', 'string', 'max:255'],
            'test_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDto(): ProxyData
    {
        $validated = $this->validated();
        $validated['letter'] = strtoupper(trim((string) $validated['letter']));
        $validated['is_active'] = $this->boolean('is_active', true);
        $validated['test_url'] = !empty($validated['test_url']) ? $validated['test_url'] : 'https://core.telegram.org/bots';

        return ProxyData::fromArray($validated);
    }
}
