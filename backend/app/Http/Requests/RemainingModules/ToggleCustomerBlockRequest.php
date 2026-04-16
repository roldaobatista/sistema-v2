<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class ToggleCustomerBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.update');
    }

    public function rules(): array
    {
        return [
            'blocked' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
