<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUpsSettingsRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission('ups.manage');
    }

    /**
     * Reglas de validación para actualizar la configuración de UPS.
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'rating_voltage' => ['nullable', 'numeric', 'min:50', 'max:500'],
            'rating_battery_voltage' => ['nullable', 'numeric', 'min:12', 'max:500'],
            'inventory_asset_id' => ['nullable', 'integer', 'exists:inventory_assets,id'],
            'telegram_alert_enabled' => ['required', 'boolean'],
            'telegram_alert_target' => ['required', 'in:owner,group'],
            'serial_port' => ['nullable', 'string', 'max:255'],
            'baud_rate' => ['nullable', 'integer', 'in:1200,2400,4800,9600,19200'],
        ];
    }
}
