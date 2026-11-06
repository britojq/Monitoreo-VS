<?php

declare(strict_types=1);

namespace App\Http\Requests\Cron;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebCheckIntervalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'interval_minutes.required' => 'Debe indicar el intervalo en minutos.',
            'interval_minutes.integer' => 'El intervalo debe ser un valor entero.',
            'interval_minutes.min' => 'El intervalo mínimo es de 1 minuto.',
            'interval_minutes.max' => 'El intervalo máximo es de 1440 minutos (24 horas).',
        ];
    }
}
