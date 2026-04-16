<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmLossReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLossReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(array_keys(CrmLossReason::CATEGORIES))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do motivo é obrigatório.',
            'category.required' => 'A categoria é obrigatória.',
        ];
    }
}
