<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ComparePurchaseQuotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.view');
    }

    public function rules(): array
    {
        return [
            'purchase_quote_ids' => 'required|array|min:2',
        ];
    }
}
