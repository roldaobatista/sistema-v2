<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.schedule.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'service_id' => $this->service_id === '' ? null : $this->service_id,
            'lat' => $this->lat === '' ? null : $this->lat,
            'lng' => $this->lng === '' ? null : $this->lng,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'service_id' => [
                'nullable',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ];
    }
}
