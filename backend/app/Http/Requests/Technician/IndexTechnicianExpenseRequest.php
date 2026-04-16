<?php

namespace App\Http\Requests\Technician;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTechnicianExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.cashbox.view');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_map(static fn (ExpenseStatus $status) => $status->value, ExpenseStatus::cases()))],
            'expense_category_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'work_order_id' => [
                'nullable',
                'integer',
                Rule::exists('work_orders', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
