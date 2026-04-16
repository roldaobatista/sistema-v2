<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StorePartsKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:product,service',
            'items.*.reference_id' => 'nullable|integer',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenantId = $this->tenantId();
            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                if (empty($item['reference_id'])) {
                    continue;
                }
                $table = ($item['type'] ?? '') === 'service' ? 'services' : 'products';
                $exists = DB::table($table)
                    ->where('id', $item['reference_id'])
                    ->where('tenant_id', $tenantId)
                    ->exists();
                if (! $exists) {
                    $validator->errors()->add("items.{$index}.reference_id", 'O item referenciado não pertence ao tenant atual.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do kit é obrigatório.',
            'items.required' => 'Informe ao menos um item no kit.',
            'items.*.type.required' => 'O tipo do item é obrigatório.',
            'items.*.type.in' => 'Tipo do item deve ser produto ou serviço.',
            'items.*.description.required' => 'A descrição do item é obrigatória.',
            'items.*.quantity.required' => 'A quantidade do item é obrigatória.',
            'items.*.unit_price.required' => 'O preço unitário do item é obrigatório.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
