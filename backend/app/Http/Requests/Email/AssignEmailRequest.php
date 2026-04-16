<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.inbox.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('user_id') && $this->input('user_id') === '') {
            $this->merge(['user_id' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
