<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaNotificationPrefsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['digest_frequency', 'quiet_hours', 'pwa_mode', 'notify_types'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
            'notify_assigned_to_me' => 'sometimes|boolean',
            'notify_created_by_me' => 'sometimes|boolean',
            'notify_watching' => 'sometimes|boolean',
            'notify_mentioned' => 'sometimes|boolean',
            'channel_in_app' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'channel_email' => ['sometimes', 'string', Rule::in(['on', 'off', 'digest'])],
            'channel_push' => ['sometimes', 'string', Rule::in(['on', 'off'])],
            'digest_frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly'])],
            'quiet_hours' => 'nullable|array',
            'quiet_hours.start' => 'nullable|date_format:H:i',
            'quiet_hours.end' => 'nullable|date_format:H:i',
            'pwa_mode' => ['nullable', 'string', Rule::in(['gestao', 'tecnico', 'vendedor'])],
            'notify_types' => 'nullable|array',
            'notify_types.*' => 'string|max:30',
        ];
    }
}
