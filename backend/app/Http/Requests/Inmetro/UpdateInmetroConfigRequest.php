<?php

namespace App\Http\Requests\Inmetro;

use App\Services\InmetroXmlImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInmetroConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.import');
    }

    public function rules(): array
    {
        $validUfs = InmetroXmlImportService::BRAZILIAN_UFS;
        $validTypes = array_keys(InmetroXmlImportService::INSTRUMENT_TYPES);

        return [
            'monitored_ufs' => 'required|array|min:1',
            'monitored_ufs.*' => ['string', 'size:2', Rule::in($validUfs)],
            'instrument_types' => 'required|array|min:1',
            'instrument_types.*' => ['string', Rule::in($validTypes)],
            'auto_sync_enabled' => 'boolean',
            'sync_interval_days' => 'integer|min:1|max:30',
        ];
    }
}
