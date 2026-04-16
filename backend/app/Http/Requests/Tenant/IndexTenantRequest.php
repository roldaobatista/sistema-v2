<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.tenant.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('per_page') && preg_match('/^-?\d+$/', (string) $this->input('per_page')) === 1) {
            $this->merge(['per_page' => (int) $this->input('per_page')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in([
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_INACTIVE,
                Tenant::STATUS_TRIAL,
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
