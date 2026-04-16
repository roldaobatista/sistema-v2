<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        return [
            'url' => 'sometimes|url|max:500',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
