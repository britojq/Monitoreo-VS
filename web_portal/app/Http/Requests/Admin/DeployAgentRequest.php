<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeployAgentRequest extends FormRequest
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
     * Reglas de validación para el despliegue del agente GLPI.
     */
    public function rules(): array
    {
        return [
            'host' => ['required', 'ip'],
            'os' => ['required', 'in:windows,linux'],
            'user' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'domain' => ['nullable', 'string'],
            'target' => ['required', 'in:auto,current,prod,dev,custom'],
            'custom_ip' => ['nullable', 'string', 'max:255', 'required_if:target,custom'],
            'action' => ['required', 'in:install,switch,status'],
        ];
    }
}
