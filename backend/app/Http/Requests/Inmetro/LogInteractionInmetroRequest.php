<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogInteractionInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.convert');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'owner_id' => ['required', Rule::exists('inmetro_owners', 'id')->where('tenant_id', $tenantId)],
            'channel' => 'required|in:whatsapp,phone,email,visit,system',
            'result' => 'required|in:interested,rejected,no_answer,scheduled,converted',
            'notes' => 'nullable|string|max:2000',
            'next_follow_up_at' => 'nullable|date',
        ];
    }
}
