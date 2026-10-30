<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', 'in:assignment,return,transfer,loan'],
            'inventory_asset_id' => ['required', 'exists:inventory_assets,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'custodian_name' => ['required', 'string', 'max:128'],
            'custodian_id_number' => ['nullable', 'string', 'max:32'],
            'custodian_department' => ['nullable', 'string', 'max:128'],
            'custodian_role' => ['nullable', 'string', 'max:96'],
            'phone_extension' => ['nullable', 'string', 'max:32'],
            'monitored_site_id' => ['nullable', 'exists:monitored_sites,id'],
            'provenance' => ['nullable', 'in:nuevo,reparacion,stock,otro'],
            'provenance_other' => ['nullable', 'string', 'max:128'],
            'checkpoint_name' => ['nullable', 'string', 'max:128'],
            'gate_operator_name' => ['nullable', 'string', 'max:128'],
            'departure_time' => ['nullable', 'string', 'max:32'],
            'entry_time' => ['nullable', 'string', 'max:32'],
            'control_number' => ['nullable', 'string', 'max:64'],
            'assigned_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'peripherals' => ['nullable', 'array'],
            'peripherals.*.type' => ['required', 'string'],
            'peripherals.*.brand' => ['nullable', 'string'],
            'peripherals.*.model' => ['nullable', 'string'],
            'peripherals.*.serial_number' => ['nullable', 'string'],
            'peripherals.*.observations' => ['nullable', 'string'],
            'specs' => ['nullable', 'array'],
        ];
    }
}
