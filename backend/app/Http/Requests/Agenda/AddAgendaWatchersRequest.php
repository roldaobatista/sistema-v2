<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddAgendaWatchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'role' => ['nullable', 'string', Rule::in(['watcher', 'approver', 'cc'])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'É necessário informar ao menos um usuário.',
        ];
    }
}
