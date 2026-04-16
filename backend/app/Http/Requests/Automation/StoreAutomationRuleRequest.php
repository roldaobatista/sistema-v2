<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('automation.rule.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('conditions') && $this->input('conditions') === '') {
            $this->merge(['conditions' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'trigger_event' => 'required|string|max:100',
            'conditions' => 'nullable|array',
            'actions' => 'required|array|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }
}
