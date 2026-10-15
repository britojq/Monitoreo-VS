<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:scheduled,in_progress,completed,canceled',
            'priority' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'technician_user_id' => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'warranty_applied' => 'nullable|boolean',
            'parts_replaced' => 'nullable|array',
            'parts_replaced.*.name' => 'required|string',
            'parts_replaced.*.serial' => 'nullable|string',
            'parts_replaced.*.cost' => 'nullable|numeric',
        ];
    }
}
