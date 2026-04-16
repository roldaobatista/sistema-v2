<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmLossReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLossReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'category' => [Rule::in(array_keys(CrmLossReason::CATEGORIES))],
            'is_active' => 'boolean',
        ];
    }
}
