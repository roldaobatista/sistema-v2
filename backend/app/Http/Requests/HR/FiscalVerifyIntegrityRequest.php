<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class FiscalVerifyIntegrityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.view');
    }

    public function rules(): array
    {
        return [
            'user_id' => 'nullable|integer|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
