<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class PowerBiDataExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.view');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['date_from', 'date_to', 'format'] as $field) {
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
            'dataset' => 'required|in:work_orders,customers,financials,products,certificates,nps',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'format' => 'nullable|in:json,csv',
        ];
    }
}
