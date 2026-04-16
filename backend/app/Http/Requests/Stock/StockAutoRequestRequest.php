<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StockAutoRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('months') && $this->input('months') === '') {
            $this->merge(['months' => null]);
        }
        if ($this->has('urgency') && ! is_array($this->input('urgency'))) {
            $this->merge(['urgency' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'months' => 'nullable|integer|min:1|max:24',
            'urgency' => 'nullable|array',
            'urgency.*' => 'in:critical,urgent,soon',
        ];
    }
}
