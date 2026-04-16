<?php

namespace App\Http\Requests\HR;

class HRClockEntriesRequest extends HRAdvancedFilterRequest
{
    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
