<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class ReplyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.inbox.send');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['cc', 'bcc'];
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
            'body' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'O corpo da resposta é obrigatório.',
        ];
    }
}
