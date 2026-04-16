<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCatalogItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $catalog = $this->route('catalog');

        return [
            'item_ids' => 'required|array',
            'item_ids.*' => [
                'integer',
                Rule::exists('service_catalog_items', 'id')->where('service_catalog_id', $catalog?->id),
            ],
        ];
    }
}
