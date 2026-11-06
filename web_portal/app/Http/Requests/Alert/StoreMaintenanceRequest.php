<?php

declare(strict_types=1);

namespace App\Http\Requests\Alert;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|string',
            'entity_id' => 'nullable|integer',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'suppress_severities' => 'nullable|array',
        ];
    }
}
