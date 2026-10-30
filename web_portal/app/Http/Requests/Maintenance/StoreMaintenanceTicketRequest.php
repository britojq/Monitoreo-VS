<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_asset_id' => 'required|exists:inventory_assets,id',
            'type' => 'required|in:preventive,corrective',
            'priority' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'technician_user_id' => 'nullable|exists:users,id',
            'cost' => 'nullable|numeric|min:0',
        ];
    }
}
