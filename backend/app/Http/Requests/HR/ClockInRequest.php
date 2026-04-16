<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'latitude' => $this->latitude === '' ? null : $this->latitude,
            'longitude' => $this->longitude === '' ? null : $this->longitude,
            'type' => $this->type === '' ? null : $this->type,
        ]);
    }

    public function rules(): array
    {
        return [
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'type' => 'nullable|in:regular,overtime,travel',
        ];
    }
}
