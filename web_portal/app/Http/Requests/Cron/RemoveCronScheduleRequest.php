<?php

declare(strict_types=1);

namespace App\Http\Requests\Cron;

use Illuminate\Foundation\Http\FormRequest;

class RemoveCronScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time' => ['required', 'string'],
        ];
    }
}
