<?php

declare(strict_types=1);

namespace App\Http\Requests\Cron;

use Illuminate\Foundation\Http\FormRequest;

class AddCronScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'time.regex' => 'El horario debe tener formato válido de 24 horas (HH:MM).',
        ];
    }
}
