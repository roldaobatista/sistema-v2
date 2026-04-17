<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.supplier.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'document', 'trade_name', 'email', 'phone', 'phone2',
            'address_zip', 'address_street', 'address_number', 'address_complement',
            'address_neighborhood', 'address_city', 'address_state', 'notes',
        ];
        $cleaned = [];

        if ($this->has('type')) {
            $type = strtolower((string) $this->input('type'));
            if ($type === 'company') {
                $cleaned['type'] = 'PJ';
            } elseif ($type === 'person') {
                $cleaned['type'] = 'PF';
            }
        }

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
        $supplier = $this->route('supplier');
        $tenantId = $this->tenantId();

        return [
            'type' => 'sometimes|in:PF,PJ',
            'name' => 'sometimes|string|max:255',
            'document' => [
                'nullable', 'string', 'max:20',
                // `document` é encrypted (cast `encrypted`) — Wave 1B usa `document_hash`.
                function (string $attribute, mixed $value, \Closure $fail) use ($supplier): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }
                    $hash = Supplier::hashSearchable('document', $value);
                    $exists = Supplier::query()
                        ->withoutGlobalScope('tenant')
                        ->where('tenant_id', $supplier->tenant_id)
                        ->whereNull('deleted_at')
                        ->where('document_hash', $hash)
                        ->where('id', '!=', $supplier->id)
                        ->exists();
                    if ($exists) {
                        $fail('já existe um fornecedor com este documento.');
                    }
                },
            ],
            'trade_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'address_zip' => 'nullable|string|max:10',
            'address_street' => 'nullable|string|max:255',
            'address_number' => 'nullable|string|max:20',
            'address_complement' => 'nullable|string|max:100',
            'address_neighborhood' => 'nullable|string|max:100',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|max:2',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'document.unique' => 'já existe um fornecedor com este documento.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
