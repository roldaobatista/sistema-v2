<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class ImportNationalHolidaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.holiday.manage');
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2000|max:2100',
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'O ano é obrigatório.',
        ];
    }
}
