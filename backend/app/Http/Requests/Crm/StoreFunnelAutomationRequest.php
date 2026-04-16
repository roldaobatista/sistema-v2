<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreFunnelAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('trigger_days') && $this->input('trigger_days') === '') {
            $this->merge(['trigger_days' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'pipeline_stage_id' => 'required|integer',
            'trigger' => 'required|string|in:on_enter,on_exit,after_days',
            'trigger_days' => 'nullable|integer|min:1',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'pipeline_stage_id.required' => 'A etapa do funil é obrigatória.',
            'subject.required' => 'O assunto é obrigatório.',
            'body.required' => 'O corpo da mensagem é obrigatório.',
        ];
    }
}
