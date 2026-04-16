<?php

namespace App\Http\Requests\SystemImprovements;

use App\Models\TechnicianSkill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicianSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'skill_name' => 'required|string|max:255',
            'category' => ['required', Rule::in(array_keys(TechnicianSkill::CATEGORIES))],
            'proficiency_level' => 'required|integer|min:1|max:5',
            'certification' => 'nullable|string',
            'certified_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ];
    }
}
