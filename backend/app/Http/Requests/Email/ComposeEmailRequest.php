<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComposeEmailRequest extends FormRequest
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'account_id' => ['required', Rule::exists('email_accounts', 'id')->where('tenant_id', $tenantId)],
            'to' => 'required|string',
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta de email é obrigatória.',
            'to.required' => 'O destinatário é obrigatório.',
            'subject.required' => 'O assunto é obrigatório.',
            'body.required' => 'O corpo do email é obrigatório.',
        ];
    }
}
