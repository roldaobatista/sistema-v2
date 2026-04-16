<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchEmailActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'ids' => 'required|array|min:1',
            'ids.*' => ['integer', Rule::exists('emails', 'id')->where('tenant_id', $tenantId)],
            'action' => 'required|in:mark_read,mark_unread,archive,star,unstar',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'É necessário informar ao menos um email.',
            'action.required' => 'A ação é obrigatória.',
            'action.in' => 'Ação inválida.',
        ];
    }
}
