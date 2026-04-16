<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.template.create');
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
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'is_shared' => 'boolean',
        ];
    }
}
