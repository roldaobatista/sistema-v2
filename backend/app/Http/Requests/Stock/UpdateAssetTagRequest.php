<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('location') && $this->input('location') === '') {
            $this->merge(['location' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:active,inactive,lost,damaged',
            'location' => 'nullable|string|max:255',
        ];
    }
}
