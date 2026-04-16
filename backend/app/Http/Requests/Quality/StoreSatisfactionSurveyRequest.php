<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSatisfactionSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customer.satisfaction.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['work_order_id', 'nps_score', 'service_rating', 'technician_rating', 'timeliness_rating', 'comment', 'channel'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'nps_score' => 'nullable|integer|min:0|max:10',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'technician_rating' => 'nullable|integer|min:1|max:5',
            'timeliness_rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'channel' => 'nullable|in:system,whatsapp,email,phone',
        ];
    }
}
