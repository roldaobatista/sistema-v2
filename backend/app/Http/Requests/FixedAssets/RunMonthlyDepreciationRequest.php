<?php

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;

class RunMonthlyDepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fixed_assets.depreciation.run');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
