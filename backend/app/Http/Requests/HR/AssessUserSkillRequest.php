<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssessUserSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.skills.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'skill_id' => ['required', Rule::exists('skills', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'level' => 'required|integer|min:1|max:5',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
