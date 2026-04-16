<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JourneyUserMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hr.journey.view')
            || (bool) $this->user()?->can('hr.journey.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'year_month' => 'required|date_format:Y-m',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O usuário é obrigatório.',
            'year_month.required' => 'O período (YYYY-MM) é obrigatório.',
        ];
    }
}
