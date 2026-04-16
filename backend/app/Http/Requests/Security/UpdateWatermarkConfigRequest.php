<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWatermarkConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'text' => $this->text === '' ? null : $this->text,
            'opacity' => $this->opacity === '' ? null : $this->opacity,
            'position' => $this->position === '' ? null : $this->position,
            'include_user_info' => $this->boolean('include_user_info'),
            'include_timestamp' => $this->boolean('include_timestamp'),
        ]);
    }

    public function rules(): array
    {
        return [
            'enabled' => 'boolean',
            'text' => 'nullable|string|max:100',
            'opacity' => 'nullable|integer|min:5|max:80',
            'position' => 'nullable|in:diagonal,top,bottom,center',
            'include_user_info' => 'boolean',
            'include_timestamp' => 'boolean',
        ];
    }
}
