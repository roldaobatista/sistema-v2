<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class TriggerBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.manage');
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:full,incremental,differential',
            'retention_days' => 'nullable|integer|min:7|max:365',
        ];
    }
}
