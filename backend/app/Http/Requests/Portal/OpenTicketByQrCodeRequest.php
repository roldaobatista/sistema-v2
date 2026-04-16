<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenTicketByQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'qr_data' => 'required|string',
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description' => 'required|string|max:1000',
            'priority' => 'nullable|in:low,medium,high,critical',
        ];
    }

    private function tenantId(): int
    {
        return (int) (auth()->user()?->current_tenant_id ?? auth()->user()?->tenant_id ?? 0);
    }
}
