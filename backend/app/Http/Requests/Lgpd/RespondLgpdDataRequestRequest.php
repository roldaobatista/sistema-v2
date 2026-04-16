<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class RespondLgpdDataRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.request.respond');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:completed,denied'],
            'response_notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
