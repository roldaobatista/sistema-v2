<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['status', 'title', 'notes', 'deadline', 'approved_supplier_id'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:draft,sent,received,approved,rejected,cancelled',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'deadline' => 'nullable|date',
            'approved_supplier_id' => 'nullable|integer',
        ];
    }
}
