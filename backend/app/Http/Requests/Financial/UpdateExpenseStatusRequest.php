<?php

namespace App\Http\Requests\Financial;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.expense.approve');
    }

    protected function prepareForValidation(): void
    {
        $routeUri = (string) $this->route()?->uri();

        if (! $this->has('status') && str_ends_with($routeUri, 'expenses/{expense}/approve')) {
            $this->merge(['status' => ExpenseStatus::APPROVED->value]);
        }

        if ($this->has('rejection_reason') && $this->input('rejection_reason') === '') {
            $this->merge(['rejection_reason' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(ExpenseStatus::cases(), 'value'))],
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }
}
