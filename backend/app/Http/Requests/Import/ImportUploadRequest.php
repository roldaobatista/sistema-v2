<?php

namespace App\Http\Requests\Import;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;

class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import.data.execute');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
            'entity_type' => 'required|in:'.implode(',', array_keys(Import::ENTITY_TYPES)),
        ];
    }
}
