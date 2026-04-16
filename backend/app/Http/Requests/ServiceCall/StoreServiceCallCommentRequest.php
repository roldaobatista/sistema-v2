<?php

namespace App\Http\Requests\ServiceCall;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceCallCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.view');
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:2000',
        ];
    }
}
