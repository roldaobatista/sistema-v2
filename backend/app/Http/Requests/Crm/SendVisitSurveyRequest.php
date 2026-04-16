<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendVisitSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'checkin_id' => ['nullable', Rule::exists('visit_checkins', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
