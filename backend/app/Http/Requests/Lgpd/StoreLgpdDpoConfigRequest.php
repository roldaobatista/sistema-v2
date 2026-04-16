<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class StoreLgpdDpoConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.dpo.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'dpo_name' => ['required', 'string', 'max:255'],
            'dpo_email' => ['required', 'email', 'max:255'],
            'dpo_phone' => ['nullable', 'string', 'max:20'],
            'is_public' => ['boolean'],
        ];
    }
}
