<?php

namespace App\Http\Requests\Inmetro;

use App\Models\Lookups\InmetroSealType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchInmetroSealRequest extends FormRequest
{
    private const SEAL_TYPE_FALLBACK = [
        'seal' => 'Lacre',
        'seal_reparo' => 'Selo Reparo',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('inmetro.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->tenant_id ?? 0);
        $allowedTypes = LookupValueResolver::allowedValues(
            InmetroSealType::class,
            self::SEAL_TYPE_FALLBACK,
            $tenantId
        );

        return [
            'type' => ['required', Rule::in($allowedTypes)],
            'start_number' => 'required|numeric',
            'end_number' => 'required|numeric|gte:start_number',
            'prefix' => 'nullable|string',
            'suffix' => 'nullable|string',
        ];
    }
}
