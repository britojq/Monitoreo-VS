<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\DTOs\ServiceData;
use App\Models\MonitoredService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'letter' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['WEB', 'DNS', 'SMTP', 'DHCP', 'CUPS', 'LDAP', 'PING', 'PROXY'])],
            'scope' => ['required', 'string', Rule::in(['corporativo', 'regional'])],
            'host_ip' => ['nullable', 'string', 'max:255'],
            'web_url' => ['nullable', 'string', 'max:500'],
            'port' => ['nullable', 'integer'],
            'credentials' => ['nullable', 'string', 'max:255'],
            'check_interface' => ['nullable', 'string', 'max:50'],
            'dns_test_domain' => ['nullable', 'string', 'max:255'],
            'normal_state_msg' => ['nullable', 'string', 'max:255'],
            'error_state_msg' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'telegram_alert_enabled' => ['nullable', 'boolean'],
            'telegram_alert_target' => ['nullable', 'string', Rule::in(['owner', 'group'])],
        ];
    }

    public function toDto(MonitoredService $service): ServiceData
    {
        $validated = $this->validated();
        if (empty($validated['letter'])) {
            $validated['letter'] = "S{$service->id}";
        } else {
            $validated['letter'] = strtoupper(trim((string) $validated['letter']));
        }
        $validated['id'] = $service->id;
        $validated['scope'] = strtolower((string) ($validated['scope'] ?? 'corporativo'));
        $validated['is_active'] = $this->boolean('is_active');
        $validated['telegram_alert_enabled'] = $this->boolean('telegram_alert_enabled');
        $target = $this->input('telegram_alert_target');
        $validated['telegram_alert_target'] = in_array($target, ['owner', 'group'], true) ? $target : 'owner';

        return ServiceData::fromArray($validated);
    }
}
