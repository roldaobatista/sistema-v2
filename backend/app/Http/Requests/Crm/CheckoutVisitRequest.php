<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.view');
    }

    public function rules(): array
    {
        return [
            'checkout_lat' => 'nullable|numeric',
            'checkout_lng' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ];
    }
}
