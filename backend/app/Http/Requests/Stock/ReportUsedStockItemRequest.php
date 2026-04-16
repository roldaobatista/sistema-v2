<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ReportUsedStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.used_stock.report');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('disposition_notes') && $this->input('disposition_notes') === '') {
            $this->merge(['disposition_notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'disposition_type' => 'required|in:return,write_off',
            'disposition_notes' => 'nullable|string|max:500',
        ];
    }
}
