<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.user.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && $this->input('phone') === '') {
            $this->merge(['phone' => null]);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => 'nullable|string|max:20',
            'current_password' => 'required_with:password|string',
            'password' => ['sometimes', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ];
    }
}
