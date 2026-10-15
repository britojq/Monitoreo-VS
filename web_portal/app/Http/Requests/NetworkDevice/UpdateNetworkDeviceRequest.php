<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkDevice;

use App\DTOs\NetworkDeviceData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNetworkDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $deviceId = $this->route('device')?->id ?? $this->route('device');

        return [
            'monitored_site_id' => ['nullable', 'exists:monitored_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'ip' => ['required', 'string', 'max:255', Rule::unique('monitored_network_devices', 'ip')->ignore($deviceId)],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => ['required', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SSH+VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'vnc_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'web_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'telnet_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_username' => ['nullable', 'string', 'max:100'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'ssh_enable_secret' => ['nullable', 'string', 'max:255'],
            'clear_ssh_credentials' => ['nullable', 'boolean'],
            'vnc_password' => ['nullable', 'string', 'max:255'],
            'clear_vnc_credentials' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'telegram_alert_enabled' => ['nullable', 'boolean'],
            'telegram_alert_target' => ['nullable', 'string', Rule::in(['owner', 'group'])],
            'status' => ['nullable', 'string', Rule::in(['assigned', 'in_stock', 'in_maintenance', 'in_transit', 'damaged', 'decommissioned'])],
            'assignment_action' => ['nullable', 'string', Rule::in(['keep', 'assign', 'release'])],
            'custodian_name' => ['nullable', 'string', 'max:128'],
            'custodian_id_number' => ['nullable', 'string', 'max:32'],
            'custodian_department' => ['nullable', 'string', 'max:128'],
            'custodian_role' => ['nullable', 'string', 'max:96'],
            'user_id' => ['nullable', 'exists:users,id'],
            'monitor_brand' => ['nullable', 'string', 'max:64'],
            'monitor_model' => ['nullable', 'string', 'max:96'],
            'monitor_serial' => ['nullable', 'string', 'max:128'],
            'remove_monitor' => ['nullable', 'boolean'],
            'keyboard_brand' => ['nullable', 'string', 'max:64'],
            'keyboard_model' => ['nullable', 'string', 'max:96'],
            'keyboard_serial' => ['nullable', 'string', 'max:128'],
            'remove_keyboard' => ['nullable', 'boolean'],
            'mouse_brand' => ['nullable', 'string', 'max:64'],
            'mouse_model' => ['nullable', 'string', 'max:96'],
            'mouse_serial' => ['nullable', 'string', 'max:128'],
            'remove_mouse' => ['nullable', 'boolean'],
            'protector_type' => ['nullable', 'string', Rule::in(['surge_protector', 'ups'])],
            'protector_brand' => ['nullable', 'string', 'max:64'],
            'protector_model' => ['nullable', 'string', 'max:96'],
            'protector_serial' => ['nullable', 'string', 'max:128'],
            'remove_protector' => ['nullable', 'boolean'],
            'has_peripherals_form' => ['nullable', 'boolean'],
            'extra_peripherals' => ['nullable', 'array'],
            'extra_peripherals.*.id' => ['nullable', 'integer'],
            'extra_peripherals.*.type' => ['nullable', 'string', 'max:50'],
            'extra_peripherals.*.brand' => ['nullable', 'string', 'max:64'],
            'extra_peripherals.*.model' => ['nullable', 'string', 'max:96'],
            'extra_peripherals.*.serial' => ['nullable', 'string', 'max:128'],
            'extra_peripherals.*.remove' => ['nullable', 'boolean'],
        ];
    }

    public function toDto(): NetworkDeviceData
    {
        $deviceId = (int) ($this->route('device')?->id ?? $this->route('device'));
        $data = $this->validated();
        $data['id'] = $deviceId;
        return NetworkDeviceData::fromArray($data);
    }
}
