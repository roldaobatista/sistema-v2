<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class ApplyContractAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contracts.contract.update');
    }

    public function rules(): array
    {
        return [
            'index_rate' => 'required|numeric|min:-50|max:100',
            'effective_date' => 'required|date',
        ];
    }
}
