<?php

namespace App\Http\Requests\FixedAssets;

use App\Models\AssetRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisposeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assetRecord = $this->route('assetRecord');

        return $assetRecord instanceof AssetRecord
            ? $this->user()->can('dispose', $assetRecord)
            : $this->user()->can('fixed_assets.asset.dispose');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'disposal_date' => ['required', 'date'],
            'reason' => ['required', Rule::in(['sale', 'loss', 'scrap', 'donation', 'theft'])],
            'disposal_value' => ['nullable', 'numeric', 'min:0'],
            'fiscal_note_id' => ['nullable', 'integer', Rule::exists('fiscal_notes', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string'],
            'approved_by' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if ((int) $this->input('approved_by') === (int) $this->user()->id) {
                    $validator->errors()->add('approved_by', 'O aprovador deve ser diferente do usuário que registra a baixa.');
                }
            },
        ];
    }
}
