<?php

namespace App\Http\Requests\Auvo;

use Illuminate\Foundation\Http\FormRequest;

class AuvoConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('auvo.import.execute');
    }

    public function rules(): array
    {
        return [
            'api_key' => 'required|string|min:5',
            'api_token' => 'required|string|min:5',
        ];
    }
}
