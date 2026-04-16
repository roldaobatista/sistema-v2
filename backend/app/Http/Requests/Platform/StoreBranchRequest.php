<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.branch.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'code', 'address_street', 'address_number', 'address_complement',
            'address_neighborhood', 'address_city', 'address_state', 'address_zip', 'phone', 'email',
        ];
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
            'name' => 'required|string|max:255',
            'code' => [
                'nullable', 'string', 'max:20',
                Rule::unique('branches', 'code')->where('tenant_id', $tenantId),
            ],
            'address_street' => 'nullable|string|max:255',
            'address_number' => 'nullable|string|max:20',
            'address_complement' => 'nullable|string|max:100',
            'address_neighborhood' => 'nullable|string|max:100',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|max:2',
            'address_zip' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ];
    }

    private function tenantId(): int
    {
        return (int) app('current_tenant_id');
    }
}
