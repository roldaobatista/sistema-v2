<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'department_id' => ['sometimes', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'level' => 'sometimes|in:junior,pleno,senior,lead,manager,director,c-level',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'level.in' => 'Nível inválido.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
