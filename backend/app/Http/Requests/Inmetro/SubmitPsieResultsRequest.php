<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPsieResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.import');
    }

    public function rules(): array
    {
        return [
            'results' => 'required|array',
            'results.*.inmetro_number' => 'required|string',
            'results.*.owner_name' => 'required|string',
        ];
    }
}
