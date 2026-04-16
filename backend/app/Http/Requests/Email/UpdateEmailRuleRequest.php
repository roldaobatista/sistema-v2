<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.rule.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->description === '' ? null : $this->description,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'conditions' => 'sometimes|array|min:1',
            'conditions.*.field' => 'required_with:conditions|string|in:from,to,subject,body,ai_category,ai_priority,ai_sentiment',
            'conditions.*.operator' => 'required_with:conditions|string|in:contains,equals,starts_with,ends_with,regex',
            'conditions.*.value' => 'required_with:conditions|string',
            'actions' => 'sometimes|array|min:1',
            'actions.*.type' => 'required_with:actions|string|in:create_task,create_chamado,notify,star,archive,mark_read,assign_category',
            'actions.*.params' => 'nullable|array',
            'priority' => 'nullable|integer|min:0|max:999',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
