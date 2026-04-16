<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseReimbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.payable.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'approval_channel' => ['required', 'string', 'in:whatsapp,email,manual'],
            'terms_accepted' => ['required', 'boolean', 'accepted'],
            'expense_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
