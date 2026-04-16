<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFiscalConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'cnae_code', 'state_registration', 'city_registration', 'fiscal_nfse_token',
            'fiscal_nfse_city', 'fiscal_nfse_rps_series',
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
        return [
            'fiscal_regime' => 'sometimes|integer|in:1,2,3,4',
            'cnae_code' => 'sometimes|nullable|string|max:20',
            'state_registration' => 'sometimes|nullable|string|max:30',
            'city_registration' => 'sometimes|nullable|string|max:30',
            'fiscal_nfse_token' => 'sometimes|nullable|string|max:255',
            'fiscal_nfse_city' => 'sometimes|nullable|string|in:rondonopolis,campo_grande',
            'fiscal_nfe_series' => 'sometimes|integer|min:1|max:999',
            'fiscal_nfe_next_number' => 'sometimes|integer|min:1',
            'fiscal_nfse_rps_series' => 'sometimes|nullable|string|max:10',
            'fiscal_nfse_rps_next_number' => 'sometimes|integer|min:1',
            'fiscal_environment' => 'sometimes|string|in:homologation,production',
        ];
    }
}
