<?php

declare(strict_types=1);

namespace App\Http\Requests\Alert;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|string',
            'entity_id' => 'nullable|integer',
            'condition_type' => 'required|string',
            'threshold_value' => 'nullable|numeric',
            'comparison' => 'nullable|string|in:gt,lt,eq,gte,lte',
            'duration_seconds' => 'nullable|integer|min:0',
            'severity' => 'required|string|in:info,warning,critical,emergency',
            'cooldown_minutes' => 'nullable|integer|min:1',
            'max_alerts_per_hour' => 'nullable|integer|min:1',
            'auto_resolve' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }
}
