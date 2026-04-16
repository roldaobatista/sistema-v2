<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class StoreLgpdDataRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.request.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'holder_name' => ['required', 'string', 'max:255'],
            'holder_email' => ['required', 'email', 'max:255'],
            'holder_document' => ['required', 'string', 'max:20'],
            'request_type' => ['required', 'string', 'in:access,deletion,portability,rectification'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
