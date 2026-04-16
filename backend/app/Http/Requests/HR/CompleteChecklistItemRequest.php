<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class CompleteChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.onboarding.manage');
    }

    public function rules(): array
    {
        return [
            'is_completed' => 'sometimes|boolean',
        ];
    }
}
