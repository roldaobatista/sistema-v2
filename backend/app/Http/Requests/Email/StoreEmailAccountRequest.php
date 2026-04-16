<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.account.create');
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
            'label' => 'required|string|max:100',
            'email_address' => 'required|email|max:255',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|min:1|max:65535',
            'imap_encryption' => 'required|in:ssl,tls,none',
            'imap_username' => 'required|string|max:255',
            'imap_password' => 'required|string|max:500',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:ssl,tls,none',
            'is_active' => 'boolean',
        ];
    }
}
