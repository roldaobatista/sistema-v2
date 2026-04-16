<?php

namespace App\Http\Requests\Import;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportExecuteRequest extends FormRequest
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
        if ($this->has('duplicate_strategy') && $this->input('duplicate_strategy') === '') {
            $this->merge(['duplicate_strategy' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'file_path' => 'required|string',
            'entity_type' => 'required|in:'.implode(',', array_keys(Import::ENTITY_TYPES)),
            'mapping' => 'required|array',
            'separator' => ['nullable', 'string', Rule::in([';', ',', 'tab'])],
            'duplicate_strategy' => 'nullable|in:'.implode(',', array_keys(Import::STRATEGIES)),
        ];
    }
}
