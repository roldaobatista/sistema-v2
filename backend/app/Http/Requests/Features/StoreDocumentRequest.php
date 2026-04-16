<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.document.create');
    }

    public function rules(): array
    {
        return [
            'document_code' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'category' => 'required|in:procedure,instruction,form,record,policy,manual',
            'version' => 'required|string|max:20',
            'description' => 'nullable|string',
            'effective_date' => 'nullable|date',
            'review_date' => 'nullable|date',
        ];
    }
}
