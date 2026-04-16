<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSequenceTraitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.sequence.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:draft,active,paused',
        ];
    }
}
