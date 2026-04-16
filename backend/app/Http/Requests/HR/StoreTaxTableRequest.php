<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.create');
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:inss,irrf,minimum_wage',
            'year' => 'required|integer|min:2020|max:2035',
            'data' => 'required|array',
        ];
    }
}
