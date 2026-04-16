<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class CreateAddendumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contracts.contract.create');
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:value_change,scope_change,term_extension,cancellation',
            'description' => 'required|string',
            'new_value' => 'nullable|numeric|min:0',
            'new_end_date' => 'nullable|date',
            'effective_date' => 'required|date',
        ];
    }
}
