<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class EmailPluginWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.manage');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['email_subject', 'email_from', 'entity_id', 'entity_type'] as $field) {
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
            'action' => 'required|in:link_email,create_ticket,lookup_customer',
            'email_subject' => 'nullable|string',
            'email_from' => 'nullable|email',
            'entity_id' => 'nullable|integer',
            'entity_type' => 'nullable|in:work_order,customer,quote',
        ];
    }
}
