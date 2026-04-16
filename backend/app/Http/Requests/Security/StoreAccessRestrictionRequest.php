<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessRestrictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.create');
    }

    public function rules(): array
    {
        return [
            'role_name' => 'required|string|max:50',
            'allowed_days' => 'required|array|min:1',
            'allowed_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ];
    }
}
