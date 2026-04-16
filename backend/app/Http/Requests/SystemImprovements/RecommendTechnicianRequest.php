<?php

namespace App\Http\Requests\SystemImprovements;

use Illuminate\Foundation\Http\FormRequest;

class RecommendTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.create');
    }

    public function rules(): array
    {
        return [
            'equipment_type' => 'nullable|string',
            'brand' => 'nullable|string',
            'service_type' => 'nullable|string',
        ];
    }
}
