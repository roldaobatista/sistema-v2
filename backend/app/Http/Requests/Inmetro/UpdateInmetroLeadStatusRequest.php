<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInmetroLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.convert');
    }

    public function rules(): array
    {
        return [
            'lead_status' => 'required|in:new,contacted,negotiating,converted,lost',
            'notes' => 'nullable|string',
        ];
    }
}
