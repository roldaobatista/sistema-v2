<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmSequence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.sequence.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'status' => [Rule::in(array_keys(CrmSequence::STATUSES))],
        ];
    }
}
