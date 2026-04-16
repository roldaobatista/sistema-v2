<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reference') && $this->input('reference') === '') {
            $this->merge(['reference' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'qr_hash' => 'required|string',
            'quantity' => 'required|numeric|min:0.01',
            'type' => ['required', Rule::in(['entry', 'exit'])],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $this->user()->current_tenant_id)],
            'reference' => 'nullable|string|max:255',
        ];
    }
}
