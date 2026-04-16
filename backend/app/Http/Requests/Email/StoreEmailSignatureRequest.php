<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.signature.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'email_account_id' => ['nullable', Rule::exists('email_accounts', 'id')->where('tenant_id', $tenantId)],
            'name' => 'required|string|max:255',
            'html_content' => 'required|string',
            'is_default' => 'boolean',
        ];
    }
}
