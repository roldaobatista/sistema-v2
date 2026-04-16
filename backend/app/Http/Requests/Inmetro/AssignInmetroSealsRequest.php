<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignInmetroSealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'technician_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'seal_ids' => 'required|array',
            'seal_ids.*' => Rule::exists('inmetro_seals', 'id')->where('tenant_id', $tenantId),
        ];
    }
}
