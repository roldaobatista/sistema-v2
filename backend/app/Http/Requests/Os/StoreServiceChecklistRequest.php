<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.checklist.manage');
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
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'items' => 'array',
            'items.*.description' => 'required|string',
            'items.*.type' => 'required|string|in:check,text,number,photo,yes_no',
            'items.*.is_required' => 'boolean',
            'items.*.order_index' => 'integer',
        ];
    }
}
