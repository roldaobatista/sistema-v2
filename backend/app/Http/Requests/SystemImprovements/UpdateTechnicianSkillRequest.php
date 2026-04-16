<?php

namespace App\Http\Requests\SystemImprovements;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTechnicianSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.update');
    }

    public function rules(): array
    {
        return [
            'proficiency_level' => 'integer|min:1|max:5',
            'certification' => 'nullable|string',
            'certified_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ];
    }
}
