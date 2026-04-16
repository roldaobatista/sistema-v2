<?php

namespace App\Http\Requests\Financial;

use App\Models\CommissionDispute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveCommissionDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.dispute.resolve');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('new_amount') && $this->input('new_amount') === '') {
            $this->merge(['new_amount' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([CommissionDispute::STATUS_ACCEPTED, CommissionDispute::STATUS_REJECTED])],
            'resolution_notes' => 'required|string|min:5|max:2000',
            'new_amount' => 'nullable|numeric|min:0',
        ];
    }
}
