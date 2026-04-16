<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.holiday.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'is_national' => 'sometimes|boolean',
            'is_recurring' => 'sometimes|boolean',
        ];
    }
}
