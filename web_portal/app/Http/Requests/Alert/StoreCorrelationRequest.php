<?php

declare(strict_types=1);

namespace App\Http\Requests\Alert;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrelationRequest extends FormRequest
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
            'parent_entity_type' => 'required|string|in:site,network_device,proxy,snmp_device',
            'parent_entity_id' => 'required|integer',
            'suppression_strategy' => 'required|string|in:suppress_all,suppress_if_parent_down,reduce_severity',
            'children' => 'nullable|array',
            'children.*.type' => 'nullable|string',
            'children.*.id' => 'nullable|integer',
        ];
    }
}
