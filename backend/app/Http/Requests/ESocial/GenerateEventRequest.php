<?php

namespace App\Http\Requests\ESocial;

use App\Models\ESocialEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.esocial.create');
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', Rule::in(array_keys(ESocialEvent::EVENT_TYPES))],
            'related_type' => ['required', 'string', Rule::in(['App\\Models\\User', 'App\\Models\\Payroll', 'App\\Models\\Rescission'])],
            'related_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
