<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitNpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'work_order_id' => [
                'nullable',
                'integer',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'score' => 'required|integer|min:0|max:10',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
