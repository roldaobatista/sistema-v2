<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmMessageTemplateRequest extends FormRequest
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
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50',
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'variables' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do template é obrigatório.',
            'slug.required' => 'O slug é obrigatório.',
            'body.required' => 'O corpo do template é obrigatório.',
        ];
    }
}
