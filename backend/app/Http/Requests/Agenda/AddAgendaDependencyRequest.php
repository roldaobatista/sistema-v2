<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddAgendaDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'depends_on_id' => ['required', Rule::exists('central_items', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'depends_on_id.required' => 'O ID do item dependente é obrigatório.',
            'depends_on_id.exists' => 'Item central não encontrado.',
        ];
    }
}
