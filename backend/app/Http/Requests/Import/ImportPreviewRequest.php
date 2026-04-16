<?php

namespace App\Http\Requests\Import;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;

class ImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import.data.execute');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('separator') && $this->input('separator') === '') {
            $this->merge(['separator' => null]);
        }
        if ($this->has('limit') && $this->input('limit') === '') {
            $this->merge(['limit' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'file_path' => 'required|string',
            'entity_type' => 'required|in:'.implode(',', array_keys(Import::ENTITY_TYPES)),
            'mapping' => 'required|array',
            'separator' => 'nullable|string',
            'limit' => 'nullable|integer|min:5|max:100',
        ];
    }
}
