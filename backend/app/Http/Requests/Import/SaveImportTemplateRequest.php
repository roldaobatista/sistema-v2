<?php

namespace App\Http\Requests\Import;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;

class SaveImportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import.data.execute');
    }

    public function rules(): array
    {
        return [
            'entity_type' => 'required|in:'.implode(',', array_keys(Import::ENTITY_TYPES)),
            'name' => 'required|string|max:100',
            'mapping' => 'required|array',
        ];
    }
}
