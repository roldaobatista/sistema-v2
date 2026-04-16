<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTollRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['highway', 'tag_number', 'payment_method', 'notes'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
            'fleet_vehicle_id' => ['required', Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'passage_date' => 'required|date',
            'toll_plaza' => 'required|string|max:150',
            'highway' => 'nullable|string|max:100',
            'value' => 'required|numeric|min:0',
            'tag_number' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|max:30',
            'notes' => 'nullable|string',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
