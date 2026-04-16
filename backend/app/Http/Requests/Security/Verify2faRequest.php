<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class Verify2faRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.view');
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6',
        ];
    }
}
