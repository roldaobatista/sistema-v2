<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;

class IndexPriceTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('advanced.price_table.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
