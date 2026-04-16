<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckinVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.view');
    }

    public function rules(): array
    {
        $tid = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
        $customerExists = Rule::exists('customers', 'id')->where(function ($q) use ($tid) {
            $q->where('tenant_id', $tid);
        });

        return [
            'customer_id' => ['required', $customerExists],
            'checkin_lat' => 'nullable|numeric',
            'checkin_lng' => 'nullable|numeric',
            'checkin_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ];
    }
}
