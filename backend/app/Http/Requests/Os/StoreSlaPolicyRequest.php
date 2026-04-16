<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class StoreSlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && $this->input('is_active') === '') {
            $this->merge(['is_active' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'response_time_minutes' => 'required|integer|min:1',
            'resolution_time_minutes' => 'required|integer|min:1',
            'priority' => 'required|string|in:low,medium,high,critical',
            'is_active' => 'boolean',
        ];
    }
}
