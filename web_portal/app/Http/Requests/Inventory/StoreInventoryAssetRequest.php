<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\DTOs\InventoryAssetData;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:128',
            'asset_tag' => 'nullable|string|max:64|unique:inventory_assets,asset_tag',
            'type' => 'required|in:desktop,laptop,server,printer,network,peripheral,router,switch,firewall,access_point,ip_phone,ups',
            'status' => 'required|in:in_stock,assigned,maintenance,damaged,disposed,unauthorized',
            'brand' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:64',
            'serial_number' => 'nullable|string|max:128|unique:inventory_assets,serial_number',
            'mac_address' => 'nullable|string|max:48',
            'ip_address' => 'nullable|ip',
            'monitored_site_id' => 'nullable|exists:monitored_sites,id',
            'location_detail' => 'nullable|string|max:191',
            'custodian_name' => 'nullable|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'operating_system' => 'nullable|string|max:128',
            'notes' => 'nullable|string',
            // Periféricos
            'include_peripherals' => 'nullable',
            'scope_option' => 'nullable|string',
            'monitor_brand' => 'nullable|string|max:64',
            'monitor_model' => 'nullable|string|max:96',
            'monitor_serial' => 'nullable|string|max:128',
            'keyboard_brand' => 'nullable|string|max:64',
            'keyboard_serial' => 'nullable|string|max:128',
            'mouse_brand' => 'nullable|string|max:64',
            'mouse_serial' => 'nullable|string|max:128',
            'surge_protector_brand' => 'nullable|string|max:64',
            'surge_protector_serial' => 'nullable|string|max:128',
            // Red & Acceso Remoto
            'ports' => 'nullable|string|max:100',
            'access_type' => 'nullable|string|in:SSH,TELNET,WEB,VNC,SSH+VNC,SIN SOPORTE',
            'access_port' => 'nullable|integer',
            'ssh_port' => 'nullable|integer',
            'vnc_port' => 'nullable|integer',
            'ssh_username' => 'nullable|string|max:64',
            'ssh_password' => 'nullable|string|max:255',
            'ssh_enable_secret' => 'nullable|string|max:255',
            'vnc_password' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'assignment_action' => 'nullable|string|in:assign,keep,release',
        ];
    }

    public function toDto(): InventoryAssetData
    {
        return InventoryAssetData::fromArray($this->validated());
    }
}
