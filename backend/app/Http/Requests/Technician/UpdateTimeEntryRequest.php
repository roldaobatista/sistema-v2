<?php

namespace App\Http\Requests\Technician;

use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.time_entry.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->description === '' ? null : $this->description,
        ]);
    }

    public function rules(): array
    {
        return [
            'started_at' => 'sometimes|date',
            'ended_at' => 'nullable|date|after:started_at',
            'type' => ['sometimes', Rule::in(array_keys(TimeEntry::TYPES))],
            'description' => 'nullable|string',
        ];
    }
}
