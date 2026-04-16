<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('portal.booking.create');
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
            'date' => 'required|date|after:today',
            'time' => 'required|string',
            'service_type' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
