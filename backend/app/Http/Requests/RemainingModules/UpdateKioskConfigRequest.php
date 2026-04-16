<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKioskConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.kiosk.manage');
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'allowed_pages' => 'nullable|array',
            'allowed_pages.*' => 'string|max:50',
            'idle_timeout_seconds' => 'nullable|integer|min:30|max:3600',
            'auto_logout' => 'boolean',
            'show_header' => 'boolean',
            'pin_code' => 'nullable|string|min:4|max:8',
        ];
    }
}
