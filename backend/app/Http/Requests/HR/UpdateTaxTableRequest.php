<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.update');
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:inss,irrf',
            'data' => 'required|array',
        ];
    }
}
