<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class TriggerRawDataBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.lab.manage');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['date_from', 'date_to'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'scope' => 'required|in:certificates,measurements,all',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ];
    }
}
