<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.pipeline.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('color') && $this->input('color') === '') {
            $this->merge(['color' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
            'probability' => 'integer|min:0|max:100',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }
}
