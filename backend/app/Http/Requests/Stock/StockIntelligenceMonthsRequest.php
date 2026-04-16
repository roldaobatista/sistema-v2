<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StockIntelligenceMonthsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('months') && $this->input('months') === '') {
            $this->merge(['months' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'months' => 'nullable|integer|min:1|max:24',
        ];
    }
}
