<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class EmailWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            '*' => 'nullable|array',
            '*.type' => 'nullable|string|max:100',
            '*.event' => 'nullable|string|max:100',
            '*.message_id' => 'nullable|string|max:255',
            '*.sg_message_id' => 'nullable|string|max:255',
            '*.reason' => 'nullable|string|max:500',
        ];
    }
}
