<?php

namespace App\Http\Requests\Os;

use App\Models\WorkOrderTimeLog;
use Illuminate\Foundation\Http\FormRequest;

class StopWorkOrderTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $timeLog = $this->route('workOrderTimeLog');

        return $timeLog instanceof WorkOrderTimeLog
            && (int) $timeLog->user_id === (int) $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
