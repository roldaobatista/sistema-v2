<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBiometricConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.biometric.manage');
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'type' => 'nullable|in:fingerprint,face_id,iris',
            'device_id' => 'nullable|string|max:255',
        ];
    }
}
