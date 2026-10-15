<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkDevice;

use App\DTOs\NetworkDeviceData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNetworkDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $typeInput = $this->input('type');
        $isPassive = in_array($typeInput, ['monitor', 'keyboard', 'mouse', 'headset', 'scanner', 'surge_protector', 'other_peripheral'], true);

        return [
            'monitored_site_id' => ['required', 'exists:monitored_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(['assigned', 'in_stock', 'in_maintenance', 'in_transit', 'damaged', 'decommissioned'])],
            'ip' => $isPassive 
                ? ['nullable', 'string', 'max:255', 'unique:monitored_network_devices,ip'] 
                : ['required', 'string', 'max:255', 'unique:monitored_network_devices,ip'],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => $isPassive 
                ? ['nullable', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SSH+VNC', 'SIN SOPORTE'])] 
                : ['required', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SSH+VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'vnc_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'web_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'telnet_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_username' => ['nullable', 'string', 'max:100'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'ssh_enable_secret' => ['nullable', 'string', 'max:255'],
            'vnc_password' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'telegram_alert_enabled' => ['nullable', 'boolean'],
            'telegram_alert_target' => ['nullable', 'string', Rule::in(['owner', 'group'])],
            'deploy_glpi' => ['nullable', 'boolean'],
            'glpi_os' => ['nullable', 'string', Rule::in(['windows', 'linux'])],
            'glpi_user' => ['nullable', 'string', 'max:100'],
            'glpi_password' => ['nullable', 'string', 'max:255'],
            'glpi_domain' => ['nullable', 'string', 'max:100'],
            'glpi_target' => ['nullable', 'string', Rule::in(['dev', 'prod'])],
            'custodian_name' => ['nullable', 'string', 'max:128'],
            'custodian_id_number' => ['nullable', 'string', 'max:32'],
            'custodian_department' => ['nullable', 'string', 'max:128'],
            'custodian_role' => ['nullable', 'string', 'max:96'],
            'user_id' => ['nullable', 'exists:users,id'],
            'has_peripherals_form' => ['nullable', 'boolean'],
            'monitor_brand' => ['nullable', 'string', 'max:64'],
            'monitor_model' => ['nullable', 'string', 'max:96'],
            'monitor_serial' => ['nullable', 'string', 'max:128'],
            'keyboard_brand' => ['nullable', 'string', 'max:64'],
            'keyboard_model' => ['nullable', 'string', 'max:96'],
            'keyboard_serial' => ['nullable', 'string', 'max:128'],
            'mouse_brand' => ['nullable', 'string', 'max:64'],
            'mouse_model' => ['nullable', 'string', 'max:96'],
            'mouse_serial' => ['nullable', 'string', 'max:128'],
            'headset_brand' => ['nullable', 'string', 'max:64'],
            'headset_model' => ['nullable', 'string', 'max:96'],
            'headset_serial' => ['nullable', 'string', 'max:128'],
            'surge_brand' => ['nullable', 'string', 'max:64'],
            'surge_model' => ['nullable', 'string', 'max:96'],
            'surge_serial' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function toDto(): NetworkDeviceData
    {
        return NetworkDeviceData::fromArray($this->validated());
    }
}
