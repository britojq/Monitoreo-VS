<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlpiEndpointsRequest extends FormRequest
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

        return method_exists($user, 'hasPermission') && $user->hasPermission('inventory.manage');
    }

    /**
     * Reglas de validación para actualizar la configuración de endpoints GLPI.
     */
    public function rules(): array
    {
        return [
            'glpi_receiver_prod_url' => ['required', 'url', 'max:255'],
            'glpi_receiver_dev_url' => ['required', 'url', 'max:255'],
            'glpi_receiver_token' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'glpi_receiver_prod_url.required' => 'La URL del receptor de Producción es obligatoria.',
            'glpi_receiver_prod_url.url' => 'La URL del receptor de Producción debe tener un formato HTTP/HTTPS válido.',
            'glpi_receiver_dev_url.required' => 'La URL del receptor de Desarrollo es obligatoria.',
            'glpi_receiver_dev_url.url' => 'La URL del receptor de Desarrollo debe tener un formato HTTP/HTTPS válido.',
            'glpi_receiver_token.required' => 'El token criptográfico del receptor es obligatorio.',
        ];
    }
}
