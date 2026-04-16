<?php

namespace App\Http\Requests\Os;

class CloseWithoutReturnRequest extends WorkOrderExecutionRequest
{
    public function rules(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
