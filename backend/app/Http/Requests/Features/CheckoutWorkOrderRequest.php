<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        return [
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ];
    }
}
