<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.holiday.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'is_national' => 'boolean',
            'is_recurring' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do feriado é obrigatório.',
            'date.required' => 'A data é obrigatória.',
        ];
    }
}
