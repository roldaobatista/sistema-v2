<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmContractRenewal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmContractRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.renewal.manage');
    }

    public function rules(): array
    {
        return [
            'status' => [Rule::in(array_keys(CrmContractRenewal::STATUSES))],
            'renewal_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
