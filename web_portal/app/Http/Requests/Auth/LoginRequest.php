<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $loginValue = trim((string) $this->input('login', $this->input('email', '')));
        $this->merge(['login' => $loginValue]);
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Debe ingresar su usuario LDAP o correo.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }
}
