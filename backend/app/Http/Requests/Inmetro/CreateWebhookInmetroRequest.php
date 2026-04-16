<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class CreateWebhookInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        return [
            'event_type' => 'required|string|max:50',
            'url' => 'required|url|max:500',
            'secret' => 'nullable|string|max:100',
        ];
    }
}
