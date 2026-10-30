<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScanSubnetRequest extends FormRequest
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

        return method_exists($user, 'hasPermission') && $user->hasPermission('inventory.deploy');
    }

    /**
     * Reglas de validación para el escaneo de subred.
     */
    public function rules(): array
    {
        return [
            'subnet' => ['required', 'string'],
        ];
    }
}
