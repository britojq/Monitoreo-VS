<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileCustodianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role ?? '', ['admin', 'operator', 'superadmin'], true);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:inventory_assets,id'],
            'custodian_name' => ['required', 'string', 'max:128'],
            'custodian_id_number' => ['nullable', 'string', 'max:32'],
            'custodian_department' => ['nullable', 'string', 'max:128'],
            'custodian_role' => ['nullable', 'string', 'max:100'],
            'phone_extension' => ['nullable', 'string', 'max:50'],
            'username' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_id.required' => 'El identificador del activo es obligatorio.',
            'asset_id.exists' => 'El activo seleccionado no existe en el inventario.',
            'custodian_name.required' => 'El nombre del funcionario es obligatorio.',
            'custodian_name.max' => 'El nombre no puede exceder los 128 caracteres.',
        ];
    }
}
