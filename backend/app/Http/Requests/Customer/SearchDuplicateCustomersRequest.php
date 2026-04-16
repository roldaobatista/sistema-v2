<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchDuplicateCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.view');
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['name', 'document', 'email'])],
        ];
    }
}
