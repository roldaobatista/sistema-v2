<?php

namespace App\Http\Requests\Logistics;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispatchRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'priority' => 'required|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
            'criteria' => 'nullable|array',
            'action' => 'nullable|array',
        ];
    }
}
