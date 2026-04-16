<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class OfflineSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.event_type' => ['required', 'string', 'in:clock_in,clock_out,break_start,break_end'],
            'events.*._offline_uuid' => ['required', 'string', 'uuid'],
            'events.*._local_timestamp' => ['required', 'date'],
            'events.*.timestamp' => ['nullable', 'date'],
            'events.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'events.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'events.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'events.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
