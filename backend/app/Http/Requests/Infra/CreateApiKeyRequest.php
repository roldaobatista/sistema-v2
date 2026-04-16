<?php

namespace App\Http\Requests\Infra;

use Illuminate\Foundation\Http\FormRequest;

class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('expires_at') && $this->input('expires_at') === '') {
            $this->merge(['expires_at' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|in:read:os,write:os,read:customers,read:stock,read:financial,read:reports',
            'expires_at' => 'nullable|date|after:today',
        ];
    }
}
