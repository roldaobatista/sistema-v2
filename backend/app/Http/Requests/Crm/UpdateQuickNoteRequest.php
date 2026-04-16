<?php

namespace App\Http\Requests\Crm;

use App\Models\QuickNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuickNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'content' => 'string',
            'sentiment' => ['nullable', Rule::in(array_keys(QuickNote::SENTIMENTS))],
            'is_pinned' => 'boolean',
            'tags' => 'nullable|array',
        ];
    }
}
