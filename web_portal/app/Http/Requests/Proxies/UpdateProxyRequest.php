<?php

declare(strict_types=1);

namespace App\Http\Requests\Proxies;

use App\DTOs\ProxyData;
use App\Models\MonitoredProxy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProxyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var MonitoredProxy|null $proxy */
        $proxy = $this->route('proxy');
        $proxyId = $proxy instanceof MonitoredProxy ? $proxy->id : (int) $this->route('proxy');

        return [
            'letter' => ['required', 'string', 'max:5', Rule::unique('monitored_proxies')->ignore($proxyId)],
            'name' => ['required', 'string', 'max:255'],
            'ip_port' => ['required', 'string', 'max:255'],
            'auth_userpass' => ['nullable', 'string', 'max:255'],
            'remove_auth' => ['nullable', 'boolean'],
            'test_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDto(MonitoredProxy $proxy): ProxyData
    {
        $validated = $this->validated();
        $validated['id'] = $proxy->id;
        $validated['letter'] = strtoupper(trim((string) $validated['letter']));
        $validated['is_active'] = $this->boolean('is_active');
        $validated['test_url'] = !empty($validated['test_url']) ? $validated['test_url'] : 'https://core.telegram.org/bots';

        if ($this->boolean('remove_auth')) {
            $validated['auth_userpass'] = null;
        } elseif (empty(trim((string) $this->input('auth_userpass')))) {
            $validated['auth_userpass'] = $proxy->auth_userpass;
        } else {
            $validated['auth_userpass'] = trim((string) $this->input('auth_userpass'));
        }

        return ProxyData::fromArray($validated);
    }
}
