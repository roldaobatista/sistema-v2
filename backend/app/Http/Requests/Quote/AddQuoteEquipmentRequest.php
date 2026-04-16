<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddQuoteEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'equipment_id' => ['required', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_id.required' => 'O equipamento é obrigatório.',
            'equipment_id.exists' => 'Equipamento não encontrado.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
