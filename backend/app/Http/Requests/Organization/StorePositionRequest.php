<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.organization.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'name' => 'required|string|max:255',
            'department_id' => ['required', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'level' => 'required|in:junior,pleno,senior,lead,manager,director,c-level',
            'description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do cargo é obrigatório.',
            'department_id.required' => 'O departamento é obrigatório.',
            'level.required' => 'O nível do cargo é obrigatório.',
            'level.in' => 'Nível inválido.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
