<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkInstrumentInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'instrument_id' => 'required|exists:inmetro_instruments,id',
            'equipment_id' => ['required', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
