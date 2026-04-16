<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:pending,approved,partially_fulfilled,fulfilled,rejected,cancelled',
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }
}
