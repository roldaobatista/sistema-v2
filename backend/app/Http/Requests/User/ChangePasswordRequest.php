<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.user.update');
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ];
    }
}
