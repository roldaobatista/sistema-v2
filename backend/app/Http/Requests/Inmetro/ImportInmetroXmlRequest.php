<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class ImportInmetroXmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.import');
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|string|in:all,competitors,instruments',
            'ufs' => 'nullable|array',
            'ufs.*' => 'string|size:2',
            'uf' => 'nullable|string|size:2',
            'instrument_types' => 'nullable|array',
            'instrument_types.*' => 'string|max:100',
        ];
    }
}
