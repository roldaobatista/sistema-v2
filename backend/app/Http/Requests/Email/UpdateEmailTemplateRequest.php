<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.template.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('subject') && $this->input('subject') === '') {
            $this->merge(['subject' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'subject' => 'nullable|string|max:255',
            'body' => 'sometimes|string',
            'is_shared' => 'boolean',
        ];
    }
}
