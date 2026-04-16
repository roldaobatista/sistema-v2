<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class AddToSyncQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.tech_sync.create');
    }

    public function rules(): array
    {
        return [
            'entity_type' => 'required|string|max:50',
            'entity_id' => 'nullable|integer',
            'action' => 'required|in:create,update,delete',
            'payload' => 'required|array',
        ];
    }
}
