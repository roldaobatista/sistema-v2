<?php

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fixed_assets.asset.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(['machinery', 'vehicle', 'equipment', 'furniture', 'it', 'tooling', 'other'])],
            'acquisition_date' => ['required', 'date'],
            'acquisition_value' => ['required', 'numeric', 'min:0.01'],
            'residual_value' => ['required', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'depreciation_method' => ['required', Rule::in(['linear', 'accelerated', 'units_produced'])],
            'location' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'nf_number' => ['nullable', 'string', 'max:50'],
            'nf_serie' => ['nullable', 'string', 'max:10'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'fleet_vehicle_id' => ['nullable', 'integer', Rule::exists('fleet_vehicles', 'id')->where('tenant_id', $tenantId)],
            'crm_deal_id' => ['nullable', 'integer', Rule::exists('crm_deals', 'id')->where('tenant_id', $tenantId)],
            'ciap_credit_type' => ['nullable', Rule::in(['icms_full', 'icms_48', 'none'])],
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $acquisitionValue = (float) $this->input('acquisition_value', 0);
                $residualValue = (float) $this->input('residual_value', 0);
                $ciapType = (string) $this->input('ciap_credit_type', 'none');
                $usefulLife = (int) $this->input('useful_life_months', 0);

                if ($residualValue > $acquisitionValue) {
                    $validator->errors()->add('residual_value', 'O valor residual não pode ser maior que o valor de aquisição.');
                }

                if ($ciapType === 'icms_48' && $usefulLife < 48) {
                    $validator->errors()->add('useful_life_months', 'Ativos com CIAP em 48 parcelas precisam de vida útil mínima de 48 meses.');
                }
            },
        ];
    }
}
