<?php

namespace App\Http\Requests\Os;

class PauseServiceRequest extends WorkOrderExecutionRequest
{
    public function rules(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
