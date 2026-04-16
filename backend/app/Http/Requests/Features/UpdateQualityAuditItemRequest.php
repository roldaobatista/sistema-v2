<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQualityAuditItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.update');
    }

    public function rules(): array
    {
        return [
            'result' => 'nullable|string|in:conform,non_conform,observation,not_applicable',
            'evidence' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
