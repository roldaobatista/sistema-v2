<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetTagRequest extends FormRequest
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
            'tag_code' => 'required|string|max:100|unique:asset_tags,tag_code',
            'tag_type' => 'required|in:rfid,qrcode,barcode',
            'taggable_type' => 'required|string|max:255',
            'taggable_id' => 'required|integer',
            'location' => 'nullable|string|max:255',
        ];
    }
}
