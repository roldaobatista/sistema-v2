<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCrmMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.message.send');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['subject', 'deal_id', 'template_id'] as $field) {
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
        $tenantId = $this->tenantId();

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'channel' => ['required', Rule::in(['whatsapp', 'email'])],
            'body' => 'required|string',
            'subject' => [
                Rule::requiredIf(fn () => $this->input('channel') === 'email' && empty($this->input('template_id'))),
                'nullable',
                'string',
                'max:255',
            ],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'template_id' => ['nullable', Rule::exists('crm_message_templates', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'variables' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'O cliente é obrigatório.',
            'channel.required' => 'O canal é obrigatório.',
            'body.required' => 'A mensagem é obrigatória.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
