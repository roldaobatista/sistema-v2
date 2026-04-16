<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaasPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.plan.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'unique:saas_plans,slug,'.($this->route('id') ?? 'NULL')],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['required', 'numeric', 'min:0'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'max:50'],
            'max_users' => ['required', 'integer', 'min:1'],
            'max_work_orders_month' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
