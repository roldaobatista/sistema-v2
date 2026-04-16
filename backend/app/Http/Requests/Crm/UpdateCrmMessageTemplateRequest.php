<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.message.send');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('subject') && $this->input('subject') === '') {
            $cleaned['subject'] = null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'subject' => 'nullable|string|max:255',
            'body' => 'sometimes|string',
            'variables' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
