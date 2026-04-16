<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class EmitBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        return [
            'source_ids' => 'required|array|min:1|max:50',
            'source_ids.*' => 'integer',
            'source_type' => 'required|in:work_order,quote',
            'note_type' => 'required|in:nfe,nfse',
        ];
    }
}
