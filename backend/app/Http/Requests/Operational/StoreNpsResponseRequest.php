<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNpsResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comment' => $this->comment === '' ? null : $this->comment,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'work_order_id' => [
                'required',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'score' => 'required|integer|min:0|max:10',
            'comment' => 'nullable|string|max:1000',
        ];
    }
}
