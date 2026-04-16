<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class CloseAuditItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.update');
    }

    public function rules(): array
    {
        return [
            'evidence' => 'required|string',
            'action_taken' => 'nullable|string',
        ];
    }
}
