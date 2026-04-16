<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.account.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['smtp_host', 'smtp_port', 'smtp_encryption'];
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
            'label' => 'sometimes|string|max:100',
            'email_address' => 'sometimes|email|max:255',
            'imap_host' => 'sometimes|string|max:255',
            'imap_port' => 'sometimes|integer|min:1|max:65535',
            'imap_encryption' => 'sometimes|in:ssl,tls,none',
            'imap_username' => 'sometimes|string|max:255',
            'imap_password' => 'sometimes|string|max:500',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:ssl,tls,none',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
