<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInmetroOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.convert');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
