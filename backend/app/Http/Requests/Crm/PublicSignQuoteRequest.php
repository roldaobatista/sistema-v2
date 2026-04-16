<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class PublicSignQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->route('token');

        return is_string($token) && $token !== '';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'signer_name' => ['required', 'string', 'max:255'],
        ];
    }
}
