<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaWatcherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'string', Rule::in(['watcher', 'approver', 'cc'])],
            'notify_status_change' => 'sometimes|boolean',
            'notify_comment' => 'sometimes|boolean',
            'notify_due_date' => 'sometimes|boolean',
            'notify_assignment' => 'sometimes|boolean',
        ];
    }
}
