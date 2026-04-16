<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.dispute.create');
    }

    public function rules(): array
    {
        $tenantId = (int) (auth()->user()->current_tenant_id ?? auth()->user()->tenant_id ?? 0);

        return [
            'commission_event_id' => [
                'required',
                Rule::exists('commission_events', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'reason' => 'required|string|min:10|max:2000',
        ];
    }
}
