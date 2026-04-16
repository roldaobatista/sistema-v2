<?php

namespace App\Http\Requests\Journey;

use App\Enums\TimeClassificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustJourneyBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'classification' => ['required', Rule::enum(TimeClassificationType::class)],
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'adjustment_reason' => 'required|string|max:500',
        ];
    }
}
