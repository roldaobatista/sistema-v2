<?php

namespace App\Http\Requests\Financial;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchUpdateExpenseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.expense.approve');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejection_reason') && $this->input('rejection_reason') === '') {
            $this->merge(['rejection_reason' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'expense_ids' => 'required|array|min:1|max:100',
            'expense_ids.*' => 'integer',
            'status' => ['required', Rule::in([ExpenseStatus::REVIEWED->value, ExpenseStatus::APPROVED->value, ExpenseStatus::REJECTED->value])],
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }
}
