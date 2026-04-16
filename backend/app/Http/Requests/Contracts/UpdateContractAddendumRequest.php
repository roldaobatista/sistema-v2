<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractAddendumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contracts.update');
    }

    public function rules(): array
    {
        return [
            'contract_id' => [
                'sometimes',
                'required',
                Rule::exists('contracts', 'id')->where('tenant_id', $this->user()->current_tenant_id),
            ],
            'type' => 'sometimes|required|string',
            'description' => 'sometimes|required|string',
            'new_value' => 'nullable|numeric',
            'new_end_date' => 'nullable|date',
            'effective_date' => 'sometimes|required|date',
            'status' => 'nullable|string',
            'approved_by' => 'nullable|exists:users,id',
            'approved_at' => 'nullable|date',
        ];
    }
}
