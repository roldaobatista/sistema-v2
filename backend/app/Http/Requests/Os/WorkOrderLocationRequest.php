<?php

namespace App\Http\Requests\Os;

class WorkOrderLocationRequest extends WorkOrderExecutionRequest
{
    public function rules(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
