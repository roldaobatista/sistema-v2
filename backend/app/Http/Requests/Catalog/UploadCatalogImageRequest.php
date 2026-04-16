<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UploadCatalogImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
        ];
    }
}
