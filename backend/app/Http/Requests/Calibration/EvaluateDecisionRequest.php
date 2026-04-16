<?php

namespace App\Http\Requests\Calibration;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('calibration.certificate.manage') ?? false;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'rule' => 'required|in:simple,guard_band,shared_risk',
            'coverage_factor_k' => 'required|numeric|min:1|max:5',
            'confidence_level' => 'nullable|numeric|min:50|max:100',

            'guard_band_mode' => 'nullable|required_if:rule,guard_band|in:k_times_u,percent_limit,fixed_abs',
            'guard_band_value' => 'nullable|required_if:rule,guard_band|numeric|min:0',

            'producer_risk_alpha' => 'nullable|required_if:rule,shared_risk|numeric|between:0.0001,0.5',
            'consumer_risk_beta' => 'nullable|required_if:rule,shared_risk|numeric|between:0.0001,0.5',

            'notes' => 'nullable|string|max:1000',
        ];
    }
}
