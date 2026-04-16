<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class GenerateESocialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.payroll.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'event_types' => ['required', 'array', 'min:1'],
            'event_types.*' => ['string', 'in:S-1200,S-2230'],
        ];
    }
}
