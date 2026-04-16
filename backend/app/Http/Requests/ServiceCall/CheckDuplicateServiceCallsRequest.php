<?php

namespace App\Http\Requests\ServiceCall;

use Illuminate\Foundation\Http\FormRequest;

class CheckDuplicateServiceCallsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.view');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer',
        ];
    }
}
