<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class EnableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.manage');
    }

    public function rules(): array
    {
        return [
            'method' => 'required|in:email,app',
            'password' => 'required|string',
        ];
    }
}
