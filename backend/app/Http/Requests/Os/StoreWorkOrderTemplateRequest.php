<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'default_items', 'checklist_id', 'priority'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'default_items' => 'nullable|array',
            'default_items.*.type' => 'required|in:product,service',
            'default_items.*.reference_id' => 'nullable|integer',
            'default_items.*.description' => 'required|string',
            'default_items.*.quantity' => 'nullable|numeric|min:0.01',
            'default_items.*.unit_price' => 'nullable|numeric|min:0',
            'checklist_id' => 'nullable|integer',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do template é obrigatório.',
        ];
    }
}
