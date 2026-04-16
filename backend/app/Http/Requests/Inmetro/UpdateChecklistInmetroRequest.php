<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'items' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
