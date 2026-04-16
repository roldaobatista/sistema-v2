<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.document.update');
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|in:procedure,instruction,form,record,policy,manual',
            'version' => 'sometimes|string|max:20',
            'description' => 'nullable|string',
            'effective_date' => 'nullable|date',
            'review_date' => 'nullable|date',
        ];
    }
}
