<?php

namespace App\Http\Requests\Tool;

use Illuminate\Foundation\Http\FormRequest;

class ToolCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('condition_in') && $this->input('condition_in') === '') {
            $this->merge(['condition_in' => null]);
        }
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'condition_in' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
