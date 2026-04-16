<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tid = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
        $customerExists = Rule::exists('customers', 'id')->where(function ($q) use ($tid) {
            $q->where('tenant_id', $tid);
        });

        return [
            'route_date' => 'required|date',
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'stops' => 'required|array|min:1',
            'stops.*.customer_id' => ['required', $customerExists],
            'stops.*.estimated_duration_minutes' => 'nullable|integer',
            'stops.*.objective' => 'nullable|string|max:500',
        ];
    }
}
