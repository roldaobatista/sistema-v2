<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            'event' => 'nullable|string|max:100',
            'data' => 'nullable|array',
            'data.*.key' => 'nullable|array',
            'data.*.key.id' => 'nullable|string|max:255',
            'data.*.key.fromMe' => 'nullable|boolean',
            'data.*.key.remoteJid' => 'nullable|string|max:255',
            'data.*.update' => 'nullable|array',
            'data.*.update.status' => 'nullable|string|max:50',
            'data.*.message' => 'nullable|array',
        ];
    }
}
